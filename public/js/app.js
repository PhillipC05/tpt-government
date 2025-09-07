/**
 * TPT Government Platform - Main Application
 * Handles application initialization, routing, and state management
 */

class App {
    constructor() {
        this.currentRoute = null;
        this.currentUser = null;
        this.isAuthenticated = false;
        this.components = new Map();

        // Initialize app
        this.init();
    }

    /**
     * Initialize the application
     */
    async init() {
        try {
            console.log('Initializing TPT Government Platform...');

            // Show loading screen
            this.showLoadingScreen();

            // Check authentication status
            await this.checkAuthentication();

            // Initialize router
            this.initRouter();

            // Initialize components
            this.initComponents();

            // Load initial route
            this.loadInitialRoute();

            // Hide loading screen
            this.hideLoadingScreen();

            console.log('TPT Government Platform initialized successfully');

        } catch (error) {
            console.error('Failed to initialize application:', error);
            this.showErrorScreen(error);
        }
    }

    /**
     * Check user authentication status
     */
    async checkAuthentication() {
        try {
            this.isAuthenticated = await API.checkAuth();

            if (this.isAuthenticated) {
                // Load user profile
                const profile = await API.getProfile();
                this.currentUser = profile.user;
                this.updateUserInterface();
            } else {
                this.showLoginForm();
            }
        } catch (error) {
            console.error('Authentication check failed:', error);
            this.showLoginForm();
        }
    }

    /**
     * Initialize router
     */
    initRouter() {
        // Handle browser navigation
        window.addEventListener('popstate', (e) => {
            this.handleRoute(URLUtils.getPath());
        });

        // Handle custom route changes
        window.addEventListener('routechange', (e) => {
            this.handleRoute(e.detail.path);
        });
    }

    /**
     * Initialize components
     */
    initComponents() {
        // Initialize sidebar toggle
        this.initSidebar();

        // Initialize notifications
        this.initNotifications();

        // Initialize logout functionality
        this.initLogout();
    }

    /**
     * Initialize sidebar functionality
     */
    initSidebar() {
        const menuToggle = DOMUtils.$('#menu-toggle');
        const sidebar = DOMUtils.$('#sidebar');
        const overlay = DOMUtils.$('#sidebar-overlay');

        if (menuToggle && sidebar && overlay) {
            DOMUtils.on(menuToggle, 'click', () => {
                DOMUtils.toggleClass(sidebar, 'open');
                DOMUtils.toggleClass(overlay, 'visible');
            });

            DOMUtils.on(overlay, 'click', () => {
                DOMUtils.removeClass(sidebar, 'open');
                DOMUtils.removeClass(overlay, 'visible');
            });
        }

        // Handle navigation links
        DOMUtils.on(document, 'click', '.nav-link', (e) => {
            e.preventDefault();
            const route = e.target.getAttribute('data-route');
            if (route) {
                this.navigate(route);
            }
        });
    }

    /**
     * Initialize notifications
     */
    initNotifications() {
        const notificationBtn = DOMUtils.$('#notifications-btn');
        const notificationCount = DOMUtils.$('#notification-count');

        if (notificationBtn) {
            DOMUtils.on(notificationBtn, 'click', () => {
                this.toggleNotifications();
            });
        }

        // Load initial notifications if authenticated
        if (this.isAuthenticated) {
            this.loadNotifications();
        }
    }

    /**
     * Initialize logout functionality
     */
    initLogout() {
        const logoutBtn = DOMUtils.$('#logout-btn');

        if (logoutBtn) {
            DOMUtils.on(logoutBtn, 'click', async () => {
                try {
                    await API.logout();
                    this.handleLogout();
                } catch (error) {
                    console.error('Logout failed:', error);
                    NotificationUtils.error('Logout failed. Please try again.');
                }
            });
        }
    }

    /**
     * Load initial route
     */
    loadInitialRoute() {
        const currentPath = URLUtils.getPath();
        this.handleRoute(currentPath);
    }

    /**
     * Handle route changes
     */
    handleRoute(path) {
        console.log('Handling route:', path);

        // Check authentication requirements
        if (this.requiresAuth(path) && !this.isAuthenticated) {
            this.navigate('/login');
            return;
        }

        // Check admin requirements
        if (this.requiresAdmin(path) && (!this.isAuthenticated || !this.hasRole('admin'))) {
            this.navigate('/dashboard');
            NotificationUtils.warning('Access denied. Admin privileges required.');
            return;
        }

        // Update active navigation
        this.updateActiveNavigation(path);

        // Load route content
        this.loadRouteContent(path);
    }

    /**
     * Check if route requires authentication
     */
    requiresAuth(path) {
        const publicRoutes = ['/', '/about', '/contact', '/login'];
        return !publicRoutes.includes(path) && !path.startsWith('/api/');
    }

    /**
     * Check if route requires admin privileges
     */
    requiresAdmin(path) {
        return path.startsWith('/admin/');
    }

    /**
     * Check if user has role
     */
    hasRole(role) {
        return this.currentUser && this.currentUser.roles && this.currentUser.roles.includes(role);
    }

    /**
     * Navigate to route
     */
    navigate(path) {
        if (URLUtils.getPath() !== path) {
            URLUtils.navigate(path);
        } else {
            this.handleRoute(path);
        }
    }

    /**
     * Load route content
     */
    async loadRouteContent(path) {
        const contentContainer = DOMUtils.$('#page-content');

        if (!contentContainer) {
            console.error('Content container not found');
            return;
        }

        // Show loading state
        ComponentRenderer.render('loading', contentContainer, {
            message: 'Loading page...'
        });

        try {
            const content = await this.fetchRouteContent(path);
            contentContainer.innerHTML = content;

            // Initialize page-specific components
            this.initPageComponents(path);

        } catch (error) {
            console.error('Failed to load route content:', error);
            contentContainer.innerHTML = `
                <div class="error-state">
                    <h2>Page Not Found</h2>
                    <p>The requested page could not be loaded.</p>
                    <button class="btn btn-primary" onclick="window.location.href='/'">Go Home</button>
                </div>
            `;
        }
    }

    /**
     * Fetch route content from server
     */
    async fetchRouteContent(path) {
        // For now, return static content based on route
        // In a full implementation, this would fetch from the server
        return this.getStaticContent(path);
    }

    /**
     * Get static content for route (placeholder)
     */
    getStaticContent(path) {
        const routes = {
            '/': `
                <div class="home-page">
                    <section class="hero">
                        <h1>Welcome to TPT Government Platform</h1>
                        <p>Modern AI-powered government services at your fingertips</p>
                        <div class="hero-actions">
                            <a href="/services" class="btn btn-primary">Browse Services</a>
                            <a href="/dashboard" class="btn btn-secondary">My Dashboard</a>
                        </div>
                    </section>

                    <section class="features">
                        <h2>Platform Features</h2>
                        <div class="features-grid">
                            <div class="feature-card">
                                <div class="feature-icon">🤖</div>
                                <h3>AI Integration</h3>
                                <p>Powered by OpenAI, Anthropic, Gemini, and OpenRouter</p>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon">📱</div>
                                <h3>PWA Support</h3>
                                <p>Works offline and installs like a native app</p>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon">🔒</div>
                                <h3>Secure & Compliant</h3>
                                <p>GDPR compliant with comprehensive audit logging</p>
                            </div>
                            <div class="feature-card">
                                <div class="feature-icon">🔧</div>
                                <h3>Modular Design</h3>
                                <p>Extensible plugin system for custom services</p>
                            </div>
                        </div>
                    </section>
                </div>
            `,

            '/dashboard': `
                <div class="dashboard-page">
                    <div class="page-header">
                        <h1>Dashboard</h1>
                        <p>Welcome back, ${this.currentUser?.name || 'User'}!</p>
                    </div>

                    <div id="dashboard-stats"></div>

                    <div class="dashboard-grid">
                        <div class="dashboard-section">
                            <h2>Recent Activity</h2>
                            <div class="activity-list">
                                <div class="activity-item">
                                    <div class="activity-icon">📝</div>
                                    <div class="activity-content">
                                        <div class="activity-title">Permit Application Submitted</div>
                                        <div class="activity-time">2 hours ago</div>
                                    </div>
                                </div>
                                <div class="activity-item">
                                    <div class="activity-icon">📄</div>
                                    <div class="activity-content">
                                        <div class="activity-title">Document Uploaded</div>
                                        <div class="activity-time">1 day ago</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="dashboard-section">
                            <h2>Quick Actions</h2>
                            <div class="quick-actions">
                                <a href="/services/permits" class="action-card">
                                    <div class="action-icon">🏢</div>
                                    <div class="action-title">Apply for Permit</div>
                                </a>
                                <a href="/services/taxes" class="action-card">
                                    <div class="action-icon">💰</div>
                                    <div class="action-title">File Taxes</div>
                                </a>
                                <a href="/documents" class="action-card">
                                    <div class="action-icon">📄</div>
                                    <div class="action-title">Upload Documents</div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            `,

            '/services': `
                <div class="services-page">
                    <div class="page-header">
                        <h1>Government Services</h1>
                        <p>Access and apply for government services online</p>
                    </div>

                    <div class="services-grid" id="services-grid">
                        <!-- Services will be loaded here -->
                    </div>
                </div>
            `,

            '/admin': `
                <div class="admin-page">
                    <div class="page-header">
                        <h1>Administration</h1>
                        <p>System administration and monitoring</p>
                    </div>

                    <div class="admin-grid">
                        <div class="admin-section">
                            <h2>System Statistics</h2>
                            <div id="admin-stats">
                                <div class="stat-item">
                                    <span class="stat-label">Total Users:</span>
                                    <span class="stat-value">1,250</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">Active Sessions:</span>
                                    <span class="stat-value">89</span>
                                </div>
                                <div class="stat-item">
                                    <span class="stat-label">API Requests Today:</span>
                                    <span class="stat-value">12,450</span>
                                </div>
                            </div>
                        </div>

                        <div class="admin-section">
                            <h2>System Health</h2>
                            <div id="system-health">
                                <div class="health-item">
                                    <span class="health-label">Database:</span>
                                    <span class="health-status status-healthy">Healthy</span>
                                </div>
                                <div class="health-item">
                                    <span class="health-label">Cache:</span>
                                    <span class="health-status status-healthy">Healthy</span>
                                </div>
                                <div class="health-item">
                                    <span class="health-label">API:</span>
                                    <span class="health-status status-healthy">Healthy</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `
        };

        return routes[path] || `
            <div class="page-not-found">
                <h1>Page Not Found</h1>
                <p>The requested page could not be found.</p>
                <a href="/" class="btn btn-primary">Go Home</a>
            </div>
        `;
    }

    /**
     * Initialize page-specific components
     */
    initPageComponents(path) {
        switch (path) {
            case '/dashboard':
                this.initDashboardComponents();
                break;
            case '/services':
                this.initServicesComponents();
                break;
            case '/admin':
                this.initAdminComponents();
                break;
        }
    }

    /**
     * Initialize dashboard components
     */
    initDashboardComponents() {
        const statsContainer = DOMUtils.$('#dashboard-stats');
        if (statsContainer) {
            ComponentRenderer.render('dashboard-stats', statsContainer);
        }
    }

    /**
     * Initialize services components
     */
    async initServicesComponents() {
        const servicesGrid = DOMUtils.$('#services-grid');
        if (!servicesGrid) return;

        try {
            const response = await API.getServices();
            const services = response.services || [];

            servicesGrid.innerHTML = services.map(service => `
                <div class="service-card" data-service-id="${service.id}">
                    <div class="service-header">
                        <div class="service-icon">${this.getServiceIcon(service.category)}</div>
                        <h3 class="service-title">${service.name}</h3>
                    </div>
                    <div class="service-description">${service.description}</div>
                    <div class="service-meta">
                        <span class="service-category">${service.category}</span>
                    </div>
                    <div class="service-actions">
                        <button class="btn btn-primary apply-btn" data-service-id="${service.id}">
                            Apply Now
                        </button>
                        <button class="btn btn-secondary learn-more-btn" data-service-id="${service.id}">
                            Learn More
                        </button>
                    </div>
                </div>
            `).join('');

            // Add event listeners
            DOMUtils.on(servicesGrid, 'click', '.apply-btn', (e) => {
                const serviceId = e.target.getAttribute('data-service-id');
                this.navigate(`/services/${serviceId}/apply`);
            });

            DOMUtils.on(servicesGrid, 'click', '.learn-more-btn', (e) => {
                const serviceId = e.target.getAttribute('data-service-id');
                this.navigate(`/services/${serviceId}`);
            });

        } catch (error) {
            console.error('Failed to load services:', error);
            servicesGrid.innerHTML = '<p>Failed to load services. Please try again.</p>';
        }
    }

    /**
     * Initialize admin components
     */
    initAdminComponents() {
        // Admin components would be initialized here
        console.log('Admin components initialized');
    }

    /**
     * Get service icon
     */
    getServiceIcon(category) {
        const icons = {
            'Business Services': '🏢',
            'Social Services': '🤝',
            'Financial Services': '💰',
            'Information Services': 'ℹ️',
            'Administrative Services': '📋'
        };
        return icons[category] || '⚙️';
    }

    /**
     * Update active navigation
     */
    updateActiveNavigation(path) {
        // Remove active class from all nav links
        DOMUtils.$$('.nav-link').forEach(link => {
            DOMUtils.removeClass(link, 'active');
        });

        // Add active class to current nav link
        const activeLink = DOMUtils.$(`.nav-link[data-route="${path}"]`);
        if (activeLink) {
            DOMUtils.addClass(activeLink, 'active');
        }
    }

    /**
     * Update user interface based on authentication state
     */
    updateUserInterface() {
        const appContainer = DOMUtils.$('#app');
        const loginContainer = DOMUtils.$('#login-form');
        const userNameElement = DOMUtils.$('#user-name');
        const adminMenuItem = DOMUtils.$('.admin-only');

        if (this.isAuthenticated && this.currentUser) {
            // Show app, hide login
            DOMUtils.toggle(appContainer, true);
            DOMUtils.toggle(loginContainer, false);

            // Update user name
            if (userNameElement) {
                userNameElement.textContent = this.currentUser.name;
            }

            // Show admin menu if user is admin
            if (adminMenuItem && this.hasRole('admin')) {
                DOMUtils.toggle(adminMenuItem, true);
            }

        } else {
            // Show login, hide app
            DOMUtils.toggle(appContainer, false);
            DOMUtils.toggle(loginContainer, true);
        }
    }

    /**
     * Show login form
     */
    showLoginForm() {
        const loginContainer = DOMUtils.$('#login-form');
        const loginFormContainer = DOMUtils.$('#login-form-container');

        if (loginFormContainer) {
            ComponentRenderer.render('login-form', loginFormContainer, {
                onSuccess: (result) => {
                    this.handleLoginSuccess(result);
                },
                onError: (error) => {
                    NotificationUtils.error('Login failed: ' + error.message);
                }
            });
        }

        this.updateUserInterface();
    }

    /**
     * Handle successful login
     */
    handleLoginSuccess(result) {
        this.isAuthenticated = true;
        this.currentUser = result.user;

        // Update UI
        this.updateUserInterface();

        // Load notifications
        this.loadNotifications();

        // Navigate to dashboard
        this.navigate('/dashboard');

        NotificationUtils.success('Welcome back, ' + this.currentUser.name + '!');
    }

    /**
     * Handle logout
     */
    handleLogout() {
        this.isAuthenticated = false;
        this.currentUser = null;

        // Clear components
        ComponentRenderer.unmount(DOMUtils.$('#dashboard-stats'));

        // Update UI
        this.updateUserInterface();

        // Navigate to home
        this.navigate('/');

        NotificationUtils.success('You have been logged out successfully.');
    }

    /**
     * Load user notifications
     */
    async loadNotifications() {
        if (!this.isAuthenticated) return;

        try {
            // Mock notifications for now
            const notifications = [
                {
                    id: 1,
                    title: 'Application Status Update',
                    message: 'Your permit application has been received and is under review.',
                    type: 'info',
                    timestamp: new Date(Date.now() - 2 * 60 * 60 * 1000).toISOString(),
                    read: false
                },
                {
                    id: 2,
                    title: 'Document Required',
                    message: 'Please upload your business license for the permit application.',
                    type: 'warning',
                    timestamp: new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString(),
                    read: false
                }
            ];

            // Update notification count
            const unreadCount = notifications.filter(n => !n.read).length;
            const notificationCount = DOMUtils.$('#notification-count');

            if (notificationCount) {
                if (unreadCount > 0) {
                    notificationCount.textContent = unreadCount;
                    DOMUtils.toggle(notificationCount, true);
                } else {
                    DOMUtils.toggle(notificationCount, false);
                }
            }

            // Store notifications
            StorageUtils.set('notifications', notifications);

        } catch (error) {
            console.error('Failed to load notifications:', error);
        }
    }

    /**
     * Toggle notifications panel
     */
    toggleNotifications() {
        const notifications = StorageUtils.get('notifications', []);
        const notificationContainer = DOMUtils.$('#notification-container');

        if (notificationContainer) {
            if (notificationContainer.children.length > 0) {
                // Hide notifications
                notificationContainer.innerHTML = '';
            } else {
                // Show notifications
                ComponentRenderer.render('notifications', notificationContainer, {
                    notifications: notifications
                });
            }
        }
    }

    /**
     * Show loading screen
     */
    showLoadingScreen() {
        const loadingScreen = DOMUtils.$('#loading-screen');
        if (loadingScreen) {
            DOMUtils.toggle(loadingScreen, true);
        }
    }

    /**
     * Hide loading screen
     */
    hideLoadingScreen() {
        const loadingScreen = DOMUtils.$('#loading-screen');
        if (loadingScreen) {
            DOMUtils.toggle(loadingScreen, false);
        }
    }

    /**
     * Show error screen
     */
    showErrorScreen(error) {
        const loadingScreen = DOMUtils.$('#loading-screen');
        if (loadingScreen) {
            loadingScreen.innerHTML = `
                <div class="error-state">
                    <h2>Application Error</h2>
                    <p>Failed to load the application. Please refresh the page.</p>
                    <button class="btn btn-primary" onclick="window.location.reload()">Refresh</button>
                </div>
            `;
        }
    }
}

// Initialize application when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.App = new App();
});

// Export for module use
if (typeof module !== 'undefined' && module.exports) {
    module.exports = App;
}

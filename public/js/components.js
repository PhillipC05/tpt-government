/**
 * TPT Government Platform - Component System
 * React-like component system without external dependencies
 */

// Base Component class
class Component {
    constructor(props = {}) {
        this.props = props;
        this.state = {};
        this.element = null;
        this.eventListeners = [];
    }

    /**
     * Set component state
     */
    setState(newState) {
        const prevState = { ...this.state };
        this.state = { ...this.state, ...newState };
        this.onStateChange(prevState, this.state);
        this.update();
    }

    /**
     * Get current state
     */
    getState() {
        return { ...this.state };
    }

    /**
     * Called when state changes
     */
    onStateChange(prevState, newState) {
        // Override in child components
    }

    /**
     * Render component
     */
    render() {
        // Override in child components
        return '';
    }

    /**
     * Update component in DOM
     */
    update() {
        if (!this.element) return;

        const newHTML = this.render();
        if (this.element.innerHTML !== newHTML) {
            this.element.innerHTML = newHTML;
            this.bindEvents();
        }
    }

    /**
     * Mount component to DOM
     */
    mount(container) {
        this.element = container;
        this.element.innerHTML = this.render();
        this.bindEvents();
        this.onMount();
    }

    /**
     * Unmount component
     */
    unmount() {
        this.onUnmount();
        this.removeEventListeners();

        if (this.element) {
            this.element.innerHTML = '';
            this.element = null;
        }
    }

    /**
     * Bind event listeners
     */
    bindEvents() {
        // Override in child components
    }

    /**
     * Remove event listeners
     */
    removeEventListeners() {
        this.eventListeners.forEach(({ element, event, handler }) => {
            element.removeEventListener(event, handler);
        });
        this.eventListeners = [];
    }

    /**
     * Add event listener
     */
    addEventListener(element, event, handler) {
        element.addEventListener(event, handler);
        this.eventListeners.push({ element, event, handler });
    }

    /**
     * Called when component is mounted
     */
    onMount() {
        // Override in child components
    }

    /**
     * Called when component is unmounted
     */
    onUnmount() {
        // Override in child components
    }

    /**
     * Find element within component
     */
    $(selector) {
        return this.element ? this.element.querySelector(selector) : null;
    }

    /**
     * Find elements within component
     */
    $$(selector) {
        return this.element ? Array.from(this.element.querySelectorAll(selector)) : [];
    }
}

// Component registry
class ComponentRegistry {
    constructor() {
        this.components = new Map();
    }

    /**
     * Register a component
     */
    register(name, componentClass) {
        this.components.set(name, componentClass);
    }

    /**
     * Get a component class
     */
    get(name) {
        return this.components.get(name);
    }

    /**
     * Check if component is registered
     */
    has(name) {
        return this.components.has(name);
    }

    /**
     * Create component instance
     */
    create(name, props = {}) {
        const ComponentClass = this.get(name);
        if (!ComponentClass) {
            throw new Error(`Component '${name}' not found`);
        }
        return new ComponentClass(props);
    }
}

// Global component registry
window.ComponentRegistry = new ComponentRegistry();

// Component renderer
class ComponentRenderer {
    constructor(registry) {
        this.registry = registry;
        this.mountedComponents = new Map();
    }

    /**
     * Render component to container
     */
    render(componentName, container, props = {}) {
        const component = this.registry.create(componentName, props);
        component.mount(container);
        this.mountedComponents.set(container, component);
        return component;
    }

    /**
     * Update component props
     */
    update(container, newProps = {}) {
        const component = this.mountedComponents.get(container);
        if (component) {
            component.props = { ...component.props, ...newProps };
            component.update();
        }
    }

    /**
     * Unmount component
     */
    unmount(container) {
        const component = this.mountedComponents.get(container);
        if (component) {
            component.unmount();
            this.mountedComponents.delete(container);
        }
    }

    /**
     * Get mounted component
     */
    get(container) {
        return this.mountedComponents.get(container);
    }
}

// Global renderer
window.ComponentRenderer = new ComponentRenderer(window.ComponentRegistry);

// Specific Components

// Loading Component
class LoadingComponent extends Component {
    constructor(props = {}) {
        super(props);
        this.state = {
            message: props.message || 'Loading...',
            showSpinner: props.showSpinner !== false
        };
    }

    render() {
        return `
            <div class="loading-state">
                ${this.state.showSpinner ? '<div class="loading-spinner"></div>' : ''}
                <p>${this.state.message}</p>
            </div>
        `;
    }
}

// Register loading component
window.ComponentRegistry.register('loading', LoadingComponent);

// Notification Component
class NotificationComponent extends Component {
    constructor(props = {}) {
        super(props);
        this.state = {
            notifications: props.notifications || []
        };
    }

    render() {
        if (this.state.notifications.length === 0) {
            return '';
        }

        return `
            <div class="notifications-list">
                ${this.state.notifications.map(notification => `
                    <div class="notification-item notification-${notification.type}" data-id="${notification.id}">
                        <div class="notification-header">
                            <span class="notification-title">${notification.title}</span>
                            <span class="notification-time">${DateUtils.relativeTime(notification.timestamp)}</span>
                        </div>
                        <div class="notification-body">
                            ${notification.message}
                        </div>
                        <div class="notification-actions">
                            <button class="btn btn-sm btn-primary mark-read" data-id="${notification.id}">
                                Mark as Read
                            </button>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    bindEvents() {
        this.$$('.mark-read').forEach(button => {
            this.addEventListener(button, 'click', (e) => {
                const notificationId = e.target.dataset.id;
                this.markAsRead(notificationId);
            });
        });
    }

    markAsRead(notificationId) {
        // Remove from local state
        this.setState({
            notifications: this.state.notifications.filter(n => n.id != notificationId)
        });

        // Mark as read in API
        API.markNotificationRead(notificationId).catch(error => {
            console.error('Failed to mark notification as read:', error);
        });
    }

    addNotification(notification) {
        const notifications = [...this.state.notifications, notification];
        this.setState({ notifications });
    }

    clearNotifications() {
        this.setState({ notifications: [] });
    }
}

// Register notification component
window.ComponentRegistry.register('notifications', NotificationComponent);

// Form Component
class FormComponent extends Component {
    constructor(props = {}) {
        super(props);
        this.state = {
            data: props.initialData || {},
            errors: {},
            loading: false
        };
    }

    render() {
        return `
            <form class="form" id="${this.props.id || 'form'}">
                ${this.renderFields()}
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" ${this.state.loading ? 'disabled' : ''}>
                        <span class="btn-text">${this.props.submitText || 'Submit'}</span>
                        ${this.state.loading ? '<div class="btn-spinner"></div>' : ''}
                    </button>
                    ${this.props.cancelText ? `<button type="button" class="btn btn-secondary cancel-btn">${this.props.cancelText}</button>` : ''}
                </div>
            </form>
        `;
    }

    renderFields() {
        // Override in child components
        return '';
    }

    bindEvents() {
        const form = this.$('form');
        if (form) {
            this.addEventListener(form, 'submit', (e) => {
                e.preventDefault();
                this.handleSubmit();
            });
        }

        const cancelBtn = this.$('.cancel-btn');
        if (cancelBtn) {
            this.addEventListener(cancelBtn, 'click', () => {
                this.handleCancel();
            });
        }
    }

    handleSubmit() {
        const formData = FormUtils.serialize(this.$('form'));
        const validation = this.validate(formData);

        if (!validation.isValid) {
            this.setState({ errors: validation.errors });
            return;
        }

        this.setState({ loading: true, errors: {} });

        this.onSubmit(formData)
            .then(result => {
                this.setState({ loading: false });
                if (this.props.onSuccess) {
                    this.props.onSuccess(result);
                }
            })
            .catch(error => {
                this.setState({
                    loading: false,
                    errors: { general: error.message }
                });
                if (this.props.onError) {
                    this.props.onError(error);
                }
            });
    }

    handleCancel() {
        if (this.props.onCancel) {
            this.props.onCancel();
        }
    }

    validate(data) {
        // Override in child components
        return { isValid: true, errors: {} };
    }

    onSubmit(data) {
        // Override in child components
        return Promise.resolve(data);
    }
}

// Login Form Component
class LoginFormComponent extends FormComponent {
    constructor(props = {}) {
        super(props);
        this.props.submitText = 'Sign In';
        this.props.id = 'login-form';
    }

    renderFields() {
        return `
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required
                       value="${this.state.data.email || ''}"
                       placeholder="your.email@gov.local">
                ${this.state.errors.email ? `<div class="error-message">${this.state.errors.email}</div>` : ''}
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required
                       placeholder="Enter your password">
                ${this.state.errors.password ? `<div class="error-message">${this.state.errors.password}</div>` : ''}
            </div>

            ${this.state.errors.general ? `<div class="error-message general-error">${this.state.errors.general}</div>` : ''}
        `;
    }

    validate(data) {
        const errors = {};

        if (!data.email) {
            errors.email = 'Email is required';
        } else if (!ValidationUtils.isValidEmail(data.email)) {
            errors.email = 'Please enter a valid email address';
        }

        if (!data.password) {
            errors.password = 'Password is required';
        }

        return {
            isValid: Object.keys(errors).length === 0,
            errors
        };
    }

    onSubmit(data) {
        return API.login(data);
    }
}

// Register login form component
window.ComponentRegistry.register('login-form', LoginFormComponent);

// Dashboard Stats Component
class DashboardStatsComponent extends Component {
    constructor(props = {}) {
        super(props);
        this.state = {
            stats: props.stats || {},
            loading: false
        };
    }

    render() {
        const { stats } = this.state;

        return `
            <div class="dashboard-stats">
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon">📝</div>
                        <div class="stat-content">
                            <div class="stat-number">${stats.applications_submitted || 0}</div>
                            <div class="stat-label">Applications Submitted</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">⏳</div>
                        <div class="stat-content">
                            <div class="stat-number">${stats.applications_pending || 0}</div>
                            <div class="stat-label">Pending Review</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">✅</div>
                        <div class="stat-content">
                            <div class="stat-number">${stats.applications_approved || 0}</div>
                            <div class="stat-label">Approved</div>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon">📄</div>
                        <div class="stat-content">
                            <div class="stat-number">${stats.documents_uploaded || 0}</div>
                            <div class="stat-label">Documents Uploaded</div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    onMount() {
        this.loadStats();
    }

    async loadStats() {
        this.setState({ loading: true });

        try {
            const response = await API.getDashboardStats();
            this.setState({
                stats: response.stats,
                loading: false
            });
        } catch (error) {
            console.error('Failed to load dashboard stats:', error);
            this.setState({ loading: false });
        }
    }
}

// Register dashboard stats component
window.ComponentRegistry.register('dashboard-stats', DashboardStatsComponent);

// Service Card Component
class ServiceCardComponent extends Component {
    constructor(props = {}) {
        super(props);
        this.state = {
            service: props.service || {},
            applied: false
        };
    }

    render() {
        const { service } = this.state;

        return `
            <div class="service-card" data-service-id="${service.id}">
                <div class="service-header">
                    <div class="service-icon">${this.getServiceIcon(service.category)}</div>
                    <h3 class="service-title">${service.name || 'Service'}</h3>
                </div>

                <div class="service-description">
                    ${service.description || 'No description available'}
                </div>

                <div class="service-meta">
                    <span class="service-category">${service.category || 'General'}</span>
                    ${service.fee ? `<span class="service-fee">$${service.fee}</span>` : ''}
                </div>

                <div class="service-actions">
                    <button class="btn btn-primary apply-btn" ${this.state.applied ? 'disabled' : ''}>
                        ${this.state.applied ? 'Applied' : 'Apply Now'}
                    </button>
                    <button class="btn btn-secondary learn-more-btn">Learn More</button>
                </div>
            </div>
        `;
    }

    bindEvents() {
        const applyBtn = this.$('.apply-btn');
        if (applyBtn) {
            this.addEventListener(applyBtn, 'click', () => {
                this.handleApply();
            });
        }

        const learnMoreBtn = this.$('.learn-more-btn');
        if (learnMoreBtn) {
            this.addEventListener(learnMoreBtn, 'click', () => {
                this.handleLearnMore();
            });
        }
    }

    handleApply() {
        // Navigate to service application page
        URLUtils.navigate(`/services/${this.state.service.id}/apply`);
    }

    handleLearnMore() {
        // Show service details modal or navigate to details page
        URLUtils.navigate(`/services/${this.state.service.id}`);
    }

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

    markAsApplied() {
        this.setState({ applied: true });
    }
}

// Register service card component
window.ComponentRegistry.register('service-card', ServiceCardComponent);

// Modal Component
class ModalComponent extends Component {
    constructor(props = {}) {
        super(props);
        this.state = {
            visible: props.visible || false,
            title: props.title || '',
            content: props.content || '',
            size: props.size || 'medium'
        };
    }

    render() {
        if (!this.state.visible) {
            return '';
        }

        return `
            <div class="modal-overlay" style="display: block;">
                <div class="modal modal-${this.state.size}">
                    <div class="modal-header">
                        <h3 class="modal-title">${this.state.title}</h3>
                        <button class="modal-close" aria-label="Close modal">×</button>
                    </div>

                    <div class="modal-body">
                        ${this.state.content}
                    </div>

                    <div class="modal-footer">
                        ${this.props.footer || '<button class="btn btn-secondary close-btn">Close</button>'}
                    </div>
                </div>
            </div>
        `;
    }

    bindEvents() {
        const overlay = this.$('.modal-overlay');
        const closeBtn = this.$('.modal-close');
        const closeBtnFooter = this.$('.close-btn');

        if (overlay) {
            this.addEventListener(overlay, 'click', (e) => {
                if (e.target === overlay) {
                    this.hide();
                }
            });
        }

        if (closeBtn) {
            this.addEventListener(closeBtn, 'click', () => {
                this.hide();
            });
        }

        if (closeBtnFooter) {
            this.addEventListener(closeBtnFooter, 'click', () => {
                this.hide();
            });
        }
    }

    show() {
        this.setState({ visible: true });
    }

    hide() {
        this.setState({ visible: false });
        if (this.props.onClose) {
            this.props.onClose();
        }
    }

    setContent(title, content, footer = null) {
        this.setState({
            title,
            content,
            footer
        });
    }
}

// Register modal component
window.ComponentRegistry.register('modal', ModalComponent);

// Lazy Loading System for Components
class LazyComponentLoader {
    constructor() {
        this.loadedComponents = new Set();
        this.loadingPromises = new Map();
        this.componentDependencies = {
            'dashboard-stats': ['api.js', 'utils.js'],
            'service-card': ['api.js', 'utils.js'],
            'notifications': ['api.js', 'utils.js'],
            'login-form': ['api.js', 'validation.js', 'form-utils.js'],
            'modal': ['utils.js']
        };
    }

    /**
     * Load component asynchronously
     */
    async loadComponent(componentName, props = {}) {
        // Check if component is already loaded
        if (this.loadedComponents.has(componentName)) {
            return window.ComponentRegistry.create(componentName, props);
        }

        // Check if component is currently loading
        if (this.loadingPromises.has(componentName)) {
            await this.loadingPromises.get(componentName);
            return window.ComponentRegistry.create(componentName, props);
        }

        // Start loading component
        const loadingPromise = this.loadComponentAsync(componentName);
        this.loadingPromises.set(componentName, loadingPromise);

        try {
            await loadingPromise;
            this.loadedComponents.add(componentName);
            this.loadingPromises.delete(componentName);
            return window.ComponentRegistry.create(componentName, props);
        } catch (error) {
            this.loadingPromises.delete(componentName);
            throw error;
        }
    }

    /**
     * Load component and its dependencies
     */
    async loadComponentAsync(componentName) {
        // Load dependencies first
        const dependencies = this.componentDependencies[componentName] || [];
        await this.loadDependencies(dependencies);

        // Load component file
        const componentPath = `/js/components/${componentName}.js`;
        await this.loadScript(componentPath);
    }

    /**
     * Load script dependencies
     */
    async loadDependencies(dependencies) {
        const loadPromises = dependencies.map(dep => {
            if (!this.isScriptLoaded(dep)) {
                return this.loadScript(`/js/${dep}`);
            }
        }).filter(Boolean);

        await Promise.all(loadPromises);
    }

    /**
     * Load script file
     */
    loadScript(src) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.src = src;
            script.async = true;

            script.onload = () => resolve();
            script.onerror = () => reject(new Error(`Failed to load script: ${src}`));

            document.head.appendChild(script);
        });
    }

    /**
     * Check if script is already loaded
     */
    isScriptLoaded(filename) {
        const scripts = document.querySelectorAll('script[src]');
        return Array.from(scripts).some(script => script.src.includes(filename));
    }

    /**
     * Preload components for better performance
     */
    preloadComponents(componentNames) {
        componentNames.forEach(name => {
            // Use requestIdleCallback if available, otherwise setTimeout
            const preloadFn = () => this.loadComponentAsync(name).catch(() => {
                // Silently fail preloading to avoid console errors
            });

            if ('requestIdleCallback' in window) {
                requestIdleCallback(preloadFn, { timeout: 2000 });
            } else {
                setTimeout(preloadFn, 100);
            }
        });
    }

    /**
     * Get loading status
     */
    isLoading(componentName) {
        return this.loadingPromises.has(componentName);
    }

    /**
     * Get loaded components
     */
    getLoadedComponents() {
        return Array.from(this.loadedComponents);
    }
}

// Global lazy loader
window.LazyComponentLoader = new LazyComponentLoader();

// Enhanced ComponentRenderer with lazy loading
class LazyComponentRenderer extends ComponentRenderer {
    constructor(registry, lazyLoader) {
        super(registry);
        this.lazyLoader = lazyLoader;
    }

    /**
     * Render component with lazy loading
     */
    async renderLazy(componentName, container, props = {}) {
        try {
            // Show loading state
            this.showLoadingState(container);

            // Load component
            const component = await this.lazyLoader.loadComponent(componentName, props);

            // Mount component
            component.mount(container);

            // Store reference
            this.mountedComponents.set(container, component);

            return component;
        } catch (error) {
            console.error(`Failed to load component ${componentName}:`, error);
            this.showErrorState(container, componentName, error);
            throw error;
        }
    }

    /**
     * Show loading state
     */
    showLoadingState(container) {
        container.innerHTML = `
            <div class="component-loading">
                <div class="loading-spinner"></div>
                <p>Loading component...</p>
            </div>
        `;
    }

    /**
     * Show error state
     */
    showErrorState(container, componentName, error) {
        container.innerHTML = `
            <div class="component-error">
                <div class="error-icon">⚠️</div>
                <p>Failed to load component: ${componentName}</p>
                <button class="btn btn-sm btn-secondary retry-btn">Retry</button>
            </div>
        `;

        // Add retry functionality
        const retryBtn = container.querySelector('.retry-btn');
        if (retryBtn) {
            retryBtn.addEventListener('click', () => {
                this.renderLazy(componentName, container);
            });
        }
    }

    /**
     * Render component with intersection observer for viewport loading
     */
    renderOnVisible(componentName, container, props = {}, rootMargin = '50px') {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.renderLazy(componentName, container, props);
                    observer.unobserve(container);
                }
            });
        }, { rootMargin });

        observer.observe(container);
    }
}

// Enhanced renderer with lazy loading
window.LazyComponentRenderer = new LazyComponentRenderer(window.ComponentRegistry, window.LazyComponentLoader);

// Intersection Observer for lazy loading components
class ComponentIntersectionObserver {
    constructor(renderer) {
        this.renderer = renderer;
        this.observer = new IntersectionObserver(
            this.handleIntersection.bind(this),
            {
                rootMargin: '100px',
                threshold: 0.1
            }
        );
        this.observedElements = new Map();
    }

    /**
     * Observe element for lazy loading
     */
    observe(element, componentName, props = {}) {
        element.setAttribute('data-component', componentName);
        element.setAttribute('data-component-props', JSON.stringify(props));
        this.observedElements.set(element, { componentName, props });
        this.observer.observe(element);
    }

    /**
     * Stop observing element
     */
    unobserve(element) {
        this.observer.unobserve(element);
        this.observedElements.delete(element);
    }

    /**
     * Handle intersection events
     */
    handleIntersection(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const element = entry.target;
                const componentData = this.observedElements.get(element);

                if (componentData) {
                    this.renderer.renderLazy(
                        componentData.componentName,
                        element,
                        componentData.props
                    );
                    this.unobserve(element);
                }
            }
        });
    }

    /**
     * Observe all elements with data-component attribute
     */
    observeAll() {
        document.querySelectorAll('[data-component]').forEach(element => {
            const componentName = element.getAttribute('data-component');
            const propsAttr = element.getAttribute('data-component-props');
            const props = propsAttr ? JSON.parse(propsAttr) : {};

            this.observe(element, componentName, props);
        });
    }
}

// Global intersection observer
window.ComponentIntersectionObserver = new ComponentIntersectionObserver(window.LazyComponentRenderer);

// Auto-initialize intersection observer when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    window.ComponentIntersectionObserver.observeAll();
});

// Utility functions for lazy loading
window.ComponentUtils = {
    /**
     * Load component on user interaction
     */
    loadOnClick(buttonSelector, componentName, container, props = {}) {
        document.addEventListener('click', (e) => {
            if (e.target.matches(buttonSelector)) {
                window.LazyComponentRenderer.renderLazy(componentName, container, props);
            }
        });
    },

    /**
     * Load component on form submission
     */
    loadOnSubmit(formSelector, componentName, container, props = {}) {
        document.addEventListener('submit', (e) => {
            if (e.target.matches(formSelector)) {
                e.preventDefault();
                window.LazyComponentRenderer.renderLazy(componentName, container, props);
            }
        });
    },

    /**
     * Load component after delay
     */
    loadAfterDelay(componentName, container, delay = 1000, props = {}) {
        setTimeout(() => {
            window.LazyComponentRenderer.renderLazy(componentName, container, props);
        }, delay);
    },

    /**
     * Load component on route change
     */
    loadOnRoute(route, componentName, container, props = {}) {
        // This would integrate with your routing system
        window.addEventListener('routeChange', (e) => {
            if (e.detail.route === route) {
                window.LazyComponentRenderer.renderLazy(componentName, container, props);
            }
        });
    }
};

// Preload critical components
window.LazyComponentLoader.preloadComponents(['loading', 'modal']);

// Export components
window.Component = Component;
window.ComponentRegistry = window.ComponentRegistry;
window.ComponentRenderer = window.ComponentRenderer;

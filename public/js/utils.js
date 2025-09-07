/**
 * TPT Government Platform - Utility Functions
 * Common utility functions for the frontend application
 */

// DOM utilities
class DOMUtils {
    /**
     * Create an element with attributes and content
     */
    static createElement(tag, attributes = {}, content = '') {
        const element = document.createElement(tag);

        // Set attributes
        Object.keys(attributes).forEach(key => {
            if (key === 'className') {
                element.className = attributes[key];
            } else if (key === 'dataset') {
                Object.keys(attributes[key]).forEach(dataKey => {
                    element.dataset[dataKey] = attributes[key][dataKey];
                });
            } else if (key.startsWith('on') && typeof attributes[key] === 'function') {
                element.addEventListener(key.substring(2).toLowerCase(), attributes[key]);
            } else {
                element.setAttribute(key, attributes[key]);
            }
        });

        // Set content
        if (typeof content === 'string') {
            element.innerHTML = content;
        } else if (content instanceof Node) {
            element.appendChild(content);
        }

        return element;
    }

    /**
     * Get element by selector with optional parent
     */
    static $(selector, parent = document) {
        return parent.querySelector(selector);
    }

    /**
     * Get elements by selector with optional parent
     */
    static $$(selector, parent = document) {
        return Array.from(parent.querySelectorAll(selector));
    }

    /**
     * Add event listener with delegation support
     */
    static on(element, event, selector, handler) {
        if (typeof selector === 'function') {
            // Direct event listener
            element.addEventListener(event, selector);
        } else {
            // Event delegation
            element.addEventListener(event, (e) => {
                const target = e.target.closest(selector);
                if (target && element.contains(target)) {
                    handler.call(target, e);
                }
            });
        }
    }

    /**
     * Toggle element visibility
     */
    static toggle(element, show = null) {
        if (show === null) {
            show = element.style.display === 'none';
        }

        element.style.display = show ? '' : 'none';
        return show;
    }

    /**
     * Add CSS class
     */
    static addClass(element, className) {
        element.classList.add(className);
    }

    /**
     * Remove CSS class
     */
    static removeClass(element, className) {
        element.classList.remove(className);
    }

    /**
     * Toggle CSS class
     */
    static toggleClass(element, className) {
        element.classList.toggle(className);
    }

    /**
     * Check if element has CSS class
     */
    static hasClass(element, className) {
        return element.classList.contains(className);
    }
}

// Form utilities
class FormUtils {
    /**
     * Serialize form data to object
     */
    static serialize(form) {
        const data = {};
        const formData = new FormData(form);

        for (let [key, value] of formData.entries()) {
            if (data[key]) {
                if (Array.isArray(data[key])) {
                    data[key].push(value);
                } else {
                    data[key] = [data[key], value];
                }
            } else {
                data[key] = value;
            }
        }

        return data;
    }

    /**
     * Validate form fields
     */
    static validate(form) {
        const inputs = form.querySelectorAll('input, select, textarea');
        let isValid = true;
        const errors = {};

        inputs.forEach(input => {
            const errorElement = form.querySelector(`#${input.id}-error`);
            if (errorElement) {
                errorElement.textContent = '';
                DOMUtils.removeClass(errorElement, 'visible');
            }

            // Check required fields
            if (input.hasAttribute('required') && !input.value.trim()) {
                isValid = false;
                errors[input.name] = 'This field is required';
                if (errorElement) {
                    errorElement.textContent = 'This field is required';
                    DOMUtils.addClass(errorElement, 'visible');
                }
            }

            // Check email format
            if (input.type === 'email' && input.value && !this.isValidEmail(input.value)) {
                isValid = false;
                errors[input.name] = 'Please enter a valid email address';
                if (errorElement) {
                    errorElement.textContent = 'Please enter a valid email address';
                    DOMUtils.addClass(errorElement, 'visible');
                }
            }

            // Check minimum length
            const minLength = input.getAttribute('minlength');
            if (minLength && input.value.length < parseInt(minLength)) {
                isValid = false;
                errors[input.name] = `Minimum length is ${minLength} characters`;
                if (errorElement) {
                    errorElement.textContent = `Minimum length is ${minLength} characters`;
                    DOMUtils.addClass(errorElement, 'visible');
                }
            }
        });

        return { isValid, errors };
    }

    /**
     * Check if email is valid
     */
    static isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Show form loading state
     */
    static setLoading(button, loading = true) {
        const spinner = button.querySelector('.btn-spinner');
        const text = button.querySelector('.btn-text');

        if (loading) {
            button.disabled = true;
            if (spinner) DOMUtils.toggle(spinner, true);
            if (text) text.textContent = 'Loading...';
        } else {
            button.disabled = false;
            if (spinner) DOMUtils.toggle(spinner, false);
            if (text) text.textContent = button.dataset.originalText || 'Submit';
        }
    }
}

// Storage utilities
class StorageUtils {
    /**
     * Set item in localStorage with JSON support
     */
    static set(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            console.error('Storage set error:', e);
        }
    }

    /**
     * Get item from localStorage with JSON parsing
     */
    static get(key, defaultValue = null) {
        try {
            const item = localStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (e) {
            console.error('Storage get error:', e);
            return defaultValue;
        }
    }

    /**
     * Remove item from localStorage
     */
    static remove(key) {
        try {
            localStorage.removeItem(key);
        } catch (e) {
            console.error('Storage remove error:', e);
        }
    }

    /**
     * Clear all localStorage
     */
    static clear() {
        try {
            localStorage.clear();
        } catch (e) {
            console.error('Storage clear error:', e);
        }
    }

    /**
     * Set item in sessionStorage
     */
    static setSession(key, value) {
        try {
            sessionStorage.setItem(key, JSON.stringify(value));
        } catch (e) {
            console.error('Session storage set error:', e);
        }
    }

    /**
     * Get item from sessionStorage
     */
    static getSession(key, defaultValue = null) {
        try {
            const item = sessionStorage.getItem(key);
            return item ? JSON.parse(item) : defaultValue;
        } catch (e) {
            console.error('Session storage get error:', e);
            return defaultValue;
        }
    }
}

// Date and time utilities
class DateUtils {
    /**
     * Format date to readable string
     */
    static format(date, format = 'short') {
        if (!(date instanceof Date)) {
            date = new Date(date);
        }

        const formats = {
            short: {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            },
            long: {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            },
            time: {
                hour: '2-digit',
                minute: '2-digit'
            }
        };

        return date.toLocaleDateString('en-US', formats[format] || formats.short);
    }

    /**
     * Get relative time (e.g., "2 hours ago")
     */
    static relativeTime(date) {
        if (!(date instanceof Date)) {
            date = new Date(date);
        }

        const now = new Date();
        const diff = now - date;
        const minutes = Math.floor(diff / 60000);
        const hours = Math.floor(diff / 3600000);
        const days = Math.floor(diff / 86400000);

        if (minutes < 1) return 'Just now';
        if (minutes < 60) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
        if (hours < 24) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
        if (days < 7) return `${days} day${days > 1 ? 's' : ''} ago`;

        return this.format(date);
    }

    /**
     * Check if date is today
     */
    static isToday(date) {
        if (!(date instanceof Date)) {
            date = new Date(date);
        }

        const today = new Date();
        return date.toDateString() === today.toDateString();
    }

    /**
     * Check if date is yesterday
     */
    static isYesterday(date) {
        if (!(date instanceof Date)) {
            date = new Date(date);
        }

        const yesterday = new Date();
        yesterday.setDate(yesterday.getDate() - 1);
        return date.toDateString() === yesterday.toDateString();
    }
}

// URL and routing utilities
class URLUtils {
    /**
     * Get current path
     */
    static getPath() {
        return window.location.pathname;
    }

    /**
     * Get query parameters
     */
    static getQueryParams() {
        const params = {};
        const searchParams = new URLSearchParams(window.location.search);

        for (let [key, value] of searchParams) {
            params[key] = value;
        }

        return params;
    }

    /**
     * Set query parameter
     */
    static setQueryParam(key, value) {
        const url = new URL(window.location);
        url.searchParams.set(key, value);
        window.history.replaceState({}, '', url);
    }

    /**
     * Remove query parameter
     */
    static removeQueryParam(key) {
        const url = new URL(window.location);
        url.searchParams.delete(key);
        window.history.replaceState({}, '', url);
    }

    /**
     * Navigate to path
     */
    static navigate(path, replace = false) {
        if (replace) {
            window.history.replaceState({}, '', path);
        } else {
            window.history.pushState({}, '', path);
        }

        // Trigger route change event
        window.dispatchEvent(new CustomEvent('routechange', { detail: { path } }));
    }

    /**
     * Go back in history
     */
    static goBack() {
        window.history.back();
    }

    /**
     * Go forward in history
     */
    static goForward() {
        window.history.forward();
    }
}

// Notification utilities
class NotificationUtils {
    /**
     * Show notification
     */
    static show(message, type = 'info', duration = 5000) {
        const container = DOMUtils.$('#notification-container');
        if (!container) return;

        const notification = DOMUtils.createElement('div', {
            className: `notification notification-${type}`
        });

        notification.innerHTML = `
            <div class="notification-content">
                <span class="notification-icon">${this.getIcon(type)}</span>
                <span class="notification-message">${message}</span>
                <button class="notification-close" aria-label="Close notification">×</button>
            </div>
        `;

        // Add close button handler
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.addEventListener('click', () => {
            this.hide(notification);
        });

        container.appendChild(notification);

        // Auto-hide after duration
        if (duration > 0) {
            setTimeout(() => {
                this.hide(notification);
            }, duration);
        }

        // Animate in
        setTimeout(() => {
            DOMUtils.addClass(notification, 'visible');
        }, 10);

        return notification;
    }

    /**
     * Hide notification
     */
    static hide(notification) {
        DOMUtils.removeClass(notification, 'visible');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }

    /**
     * Get notification icon
     */
    static getIcon(type) {
        const icons = {
            success: '✓',
            error: '✕',
            warning: '⚠',
            info: 'ℹ'
        };
        return icons[type] || icons.info;
    }

    /**
     * Show loading notification
     */
    static loading(message = 'Loading...') {
        return this.show(message, 'info', 0);
    }

    /**
     * Show success notification
     */
    static success(message) {
        return this.show(message, 'success');
    }

    /**
     * Show error notification
     */
    static error(message) {
        return this.show(message, 'error', 7000);
    }

    /**
     * Show warning notification
     */
    static warning(message) {
        return this.show(message, 'warning');
    }
}

// Validation utilities
class ValidationUtils {
    /**
     * Validate email address
     */
    static isValidEmail(email) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(email);
    }

    /**
     * Validate phone number
     */
    static isValidPhone(phone) {
        const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
        return phoneRegex.test(phone.replace(/[\s\-\(\)]/g, ''));
    }

    /**
     * Validate postal code
     */
    static isValidPostalCode(code, country = 'US') {
        const patterns = {
            US: /^\d{5}(-\d{4})?$/,
            CA: /^[A-Za-z]\d[A-Za-z][ -]?\d[A-Za-z]\d$/,
            UK: /^[A-Za-z]{1,2}\d[A-Za-z\d]?\s?\d[A-Za-z]{2}$/
        };

        return patterns[country] ? patterns[country].test(code) : true;
    }

    /**
     * Sanitize string input
     */
    static sanitizeString(input) {
        return input.replace(/[<>]/g, '').trim();
    }

    /**
     * Check password strength
     */
    static checkPasswordStrength(password) {
        let strength = 0;
        const checks = [
            password.length >= 8,
            /[a-z]/.test(password),
            /[A-Z]/.test(password),
            /\d/.test(password),
            /[^a-zA-Z\d]/.test(password)
        ];

        checks.forEach(check => {
            if (check) strength++;
        });

        return {
            score: strength,
            strength: strength < 3 ? 'weak' : strength < 4 ? 'medium' : 'strong',
            checks: {
                length: checks[0],
                lowercase: checks[1],
                uppercase: checks[2],
                number: checks[3],
                special: checks[4]
            }
        };
    }
}

// Export utilities
window.DOMUtils = DOMUtils;
window.FormUtils = FormUtils;
window.StorageUtils = StorageUtils;
window.DateUtils = DateUtils;
window.URLUtils = URLUtils;
window.NotificationUtils = NotificationUtils;
window.ValidationUtils = ValidationUtils;

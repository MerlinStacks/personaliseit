/**
 * Toast - Lightweight toast notification component
 * 
 * Provides non-blocking notifications for success, error, and info messages.
 * Auto-dismisses after configurable duration.
 * 
 * @module Toast
 */
import React, { useState, useEffect, useCallback, createContext, useContext } from 'react';

/**
 * Toast context for global access
 */
const ToastContext = createContext(null);

/**
 * Generate unique ID for toast
 */
let toastId = 0;
const getToastId = () => `toast-${++toastId}`;

/**
 * Individual toast component
 */
const ToastItem = ({ id, message, type = 'info', description, onDismiss }) => {
    const [isExiting, setIsExiting] = useState(false);

    const handleDismiss = useCallback(() => {
        setIsExiting(true);
        setTimeout(() => onDismiss(id), 200);
    }, [id, onDismiss]);

    useEffect(() => {
        const timer = setTimeout(handleDismiss, 4000);
        return () => clearTimeout(timer);
    }, [handleDismiss]);

    const icons = {
        success: 'dashicons-yes-alt',
        error: 'dashicons-warning',
        info: 'dashicons-info',
        warning: 'dashicons-flag'
    };

    return (
        <div
            className={`pi-toast pi-toast--${type} ${isExiting ? 'pi-toast--exiting' : ''}`}
            role="alert"
            aria-live="assertive"
        >
            <span className={`dashicons ${icons[type] || icons.info}`} aria-hidden="true" />
            <div className="pi-toast__content">
                <span className="pi-toast__message">{message}</span>
                {description && <span className="pi-toast__description">{description}</span>}
            </div>
            <button
                type="button"
                className="pi-toast__dismiss"
                onClick={handleDismiss}
                aria-label="Dismiss"
            >
                <span className="dashicons dashicons-no-alt" aria-hidden="true" />
            </button>
        </div>
    );
};

/**
 * Toast container component
 */
const ToastContainer = ({ toasts, removeToast }) => {
    if (toasts.length === 0) return null;

    return (
        <div className="pi-toast-container" aria-label="Notifications">
            {toasts.map(toast => (
                <ToastItem key={toast.id} {...toast} onDismiss={removeToast} />
            ))}
        </div>
    );
};

/**
 * Toast provider component - wraps app to enable global toasts
 */
export const ToastProvider = ({ children }) => {
    const [toasts, setToasts] = useState([]);

    const addToast = useCallback((message, options = {}) => {
        const id = getToastId();
        const toast = { id, message, ...options };
        setToasts(prev => [...prev, toast]);
        return id;
    }, []);

    const removeToast = useCallback((id) => {
        setToasts(prev => prev.filter(t => t.id !== id));
    }, []);

    // Convenience methods
    const toast = {
        success: (message, description) => addToast(message, { type: 'success', description }),
        error: (message, description) => addToast(message, { type: 'error', description }),
        info: (message, description) => addToast(message, { type: 'info', description }),
        warning: (message, description) => addToast(message, { type: 'warning', description })
    };

    return (
        <ToastContext.Provider value={toast}>
            {children}
            <ToastContainer toasts={toasts} removeToast={removeToast} />
        </ToastContext.Provider>
    );
};

/**
 * Hook to use toast notifications
 * @returns {Object} Toast methods: success, error, info, warning
 */
export const useToast = () => {
    const context = useContext(ToastContext);
    if (!context) {
        // No-op fallback if component used outside ToastProvider
        // Why: Prevents console noise in production per coding standards
        const noop = () => { };
        return { success: noop, error: noop, info: noop, warning: noop };
    }
    return context;
};

/**
 * Standalone toast function for use outside React components
 * Creates a temporary toast container if needed
 */
export const showToast = (message, type = 'info', description = '') => {
    // Find or create toast container
    let container = document.getElementById('pi-standalone-toasts');
    if (!container) {
        container = document.createElement('div');
        container.id = 'pi-standalone-toasts';
        container.className = 'pi-toast-container';
        container.setAttribute('aria-label', 'Notifications');
        document.body.appendChild(container);
    }

    const id = getToastId();
    const icons = {
        success: 'dashicons-yes-alt',
        error: 'dashicons-warning',
        info: 'dashicons-info',
        warning: 'dashicons-flag'
    };

    // Create toast element
    const toast = document.createElement('div');
    toast.className = `pi-toast pi-toast--${type}`;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.innerHTML = `
        <span class="dashicons ${icons[type] || icons.info}" aria-hidden="true"></span>
        <div class="pi-toast__content">
            <span class="pi-toast__message">${message}</span>
            ${description ? `<span class="pi-toast__description">${description}</span>` : ''}
        </div>
        <button type="button" class="pi-toast__dismiss" aria-label="Dismiss">
            <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
        </button>
    `;

    // Add dismiss handler
    toast.querySelector('.pi-toast__dismiss').addEventListener('click', () => {
        toast.classList.add('pi-toast--exiting');
        setTimeout(() => toast.remove(), 200);
    });

    container.appendChild(toast);

    // Auto-dismiss after 4 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.classList.add('pi-toast--exiting');
            setTimeout(() => toast.remove(), 200);
        }
    }, 4000);
};

// Convenience exports
showToast.success = (msg, desc) => showToast(msg, 'success', desc);
showToast.error = (msg, desc) => showToast(msg, 'error', desc);
showToast.info = (msg, desc) => showToast(msg, 'info', desc);
showToast.warning = (msg, desc) => showToast(msg, 'warning', desc);

export default { ToastProvider, useToast, showToast };

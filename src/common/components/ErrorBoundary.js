/**
 * ErrorBoundary - React error boundary for crash protection
 * 
 * Catches JavaScript errors in child components, logs them,
 * and displays a fallback UI.
 * 
 * @module ErrorBoundary
 */
import React, { Component } from 'react';
import { __ } from '@wordpress/i18n';

/**
 * Error boundary component class
 * Uses class component as hooks don't support componentDidCatch
 */
class ErrorBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { hasError: false, error: null, errorInfo: null };
    }

    /**
     * Update state when error occurs
     */
    static getDerivedStateFromError(error) {
        return { hasError: true, error };
    }

    /**
     * Log error details
     */
    componentDidCatch(error, errorInfo) {
        this.setState({ errorInfo });

        // Log to console in development
        console.error('[PersonaliseIt Error]', error);
        console.error('Component Stack:', errorInfo?.componentStack);

        // Could send to error reporting service here
        if (this.props.onError) {
            this.props.onError(error, errorInfo);
        }
    }

    /**
     * Reset error state
     */
    handleReset = () => {
        this.setState({ hasError: false, error: null, errorInfo: null });
    };

    render() {
        const { hasError, error } = this.state;
        const { children, fallback, componentName = 'component' } = this.props;

        if (hasError) {
            // Custom fallback provided
            if (fallback) {
                return typeof fallback === 'function'
                    ? fallback({ error, reset: this.handleReset })
                    : fallback;
            }

            // Default fallback UI
            return (
                <div className="pi-error-boundary" role="alert">
                    <div className="pi-error-boundary__content">
                        <span
                            className="dashicons dashicons-warning"
                            aria-hidden="true"
                        ></span>
                        <div className="pi-error-boundary__text">
                            <p className="pi-error-boundary__title">
                                {__('Something went wrong', 'personaliseit')}
                            </p>
                            <p className="pi-error-boundary__message">
                                {__('The', 'personaliseit')} {componentName} {__('encountered an error.', 'personaliseit')}
                            </p>
                        </div>
                        <button
                            type="button"
                            className="pi-btn secondary small"
                            onClick={this.handleReset}
                        >
                            {__('Try Again', 'personaliseit')}
                        </button>
                    </div>
                </div>
            );
        }

        return children;
    }
}

export default ErrorBoundary;

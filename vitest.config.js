import { defineConfig } from 'vitest/config';

/**
 * Vitest configuration for PersonaliseIt plugin
 * 
 * Uses jsdom for React component testing
 * Mocks WordPress dependencies
 */
export default defineConfig({
    test: {
        environment: 'jsdom',
        globals: true,
        setupFiles: ['./tests/setup.js'],
        include: ['tests/**/*.test.js', 'src/**/*.test.js'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'html'],
            include: ['src/**/*.js'],
            exclude: [
                'src/**/*.test.js',
                'src/**/index.js',  // Barrel files
                'src/**/*.scss'
            ]
        },
        alias: {
            '@wordpress/element': 'react',
            '@wordpress/i18n': './tests/mocks/i18n.js',
            '@wordpress/api-fetch': './tests/mocks/api-fetch.js',
            '@wordpress/components': './tests/mocks/components.js'
        }
    }
});

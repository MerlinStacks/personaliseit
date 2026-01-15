/**
 * Vitest test setup
 * 
 * Configures testing library and global mocks
 */
import '@testing-library/jest-dom';

// Mock window.personaliseitData
global.personaliseitData = {
    restUrl: '/wp-json/',
    nonce: 'test-nonce',
    settings: {
        enableAiGenerate: true,
        enableAiStyle: true,
        enableSpotify: false
    },
    config: {
        layers: [],
        views: [{ id: 'front', label: 'Front' }]
    }
};

// Mock fetch
global.fetch = vi.fn(() =>
    Promise.resolve({
        ok: true,
        json: () => Promise.resolve({})
    })
);

// Mock URL.createObjectURL
global.URL.createObjectURL = vi.fn(() => 'blob:mock-url');
global.URL.revokeObjectURL = vi.fn();

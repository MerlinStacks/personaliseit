/**
 * Mock for @wordpress/api-fetch
 */
const apiFetch = vi.fn((options) => {
    // Return test data based on path
    if (options.path === '/wp/v2/settings') {
        return Promise.resolve({
            personaliseit_canvas_width: 800,
            personaliseit_canvas_height: 600
        });
    }
    return Promise.resolve({});
});

export default apiFetch;

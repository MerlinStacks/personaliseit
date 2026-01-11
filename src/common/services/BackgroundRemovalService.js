import imglyRemoveBackground from '@imgly/background-removal';

/**
 * BackgroundRemovalService
 * Handles local background removal using @imgly/background-removal (WebAssembly).
 */
const BackgroundRemovalService = {
    /**
     * Remove background from an image URL or Blob.
     * @param {string|Blob|File} imageSource - The image to process.
     * @param {Object} options - Configuration options.
     * @returns {Promise<Blob>} - The processed image as a PNG Blob.
     */
    removeBackground: async (imageSource, options = {}) => {
        try {
            const config = {
                progress: (key, current, total) => {
                    if (options.onProgress) {
                        options.onProgress(current / total);
                    }
                },
                model: 'medium', // Balance between speed and quality
                ...options
            };

            const blob = await imglyRemoveBackground(imageSource, config);
            return blob;
        } catch (error) {
            console.error('BackgroundRemovalService: Failed to remove background', error);
            throw error;
        }
    },

    /**
     * Preload models to warm up the cache.
     */
    preload: async () => {
        // Trigger a lightweight removal or model fetch if supported by library in future.
        // Currently, the best way is to run a dummy/small image or just rely on first use.
        // This is a stub for future optimization.
    }
};

export default BackgroundRemovalService;

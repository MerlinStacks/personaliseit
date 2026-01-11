import apiFetch from '@wordpress/api-fetch';

/**
 * FontService
 * Centralized service for fetching, managing, and loading fonts.
 */
const FontService = {
    // Cache for loaded fonts to prevent duplicate style tags
    loadedFonts: new Set(),

    /**
     * Fetch all available fonts (System + Uploaded)
     * @returns {Promise<Array>}
     */
    getFonts: async () => {
        try {
            const fonts = await apiFetch({ path: '/personaliseit/v1/fonts' });
            return fonts || [];
        } catch (error) {
            console.error('FontService: Failed to fetch fonts', error);
            // Return defaults if API fails
            return [
                { id: 'arial', family: 'Arial', title: 'Arial' },
                { id: 'times', family: 'Times New Roman', title: 'Times New Roman' },
                { id: 'courier', family: 'Courier New', title: 'Courier New' }
            ];
        }
    },

    /**
     * Upload a new font or variation
     * @param {File} file 
     * @param {Object} args { title, family, source, url } or { fontId } for variation
     */
    uploadFont: async (file, args = {}) => {
        const formData = new FormData();
        formData.append('file', file);
        if (args.title) formData.append('title', args.title);

        const path = args.fontId
            ? `/personaliseit/v1/fonts/${args.fontId}`
            : '/personaliseit/v1/fonts';

        return await apiFetch({
            path,
            method: 'POST',
            body: formData,
        });
    },

    /**
     * Add Google Font by URL
     */
    addGoogleFont: async (family, url) => {
        return await apiFetch({
            path: '/personaliseit/v1/fonts',
            method: 'POST',
            data: {
                source: 'google',
                title: family,
                family: family,
                url: url
            }
        });
    },

    /**
     * Delete a font
     */
    deleteFont: async (id) => {
        return await apiFetch({
            path: `/personaliseit/v1/fonts/${id}`,
            method: 'DELETE',
        });
    },

    /**
     * Inject properly formatted @font-face styles into the document head.
     * Idempotent: checks if style already exists.
     * @param {Array} fonts list of font objects
     */
    loadFontsIntoDom: (fonts) => {
        if (!fonts || !Array.isArray(fonts)) return;

        fonts.forEach((font) => {
            // Identifier for cache/duplicates
            const fontId = `pi-font-${font.family.replace(/\s+/g, '-').toLowerCase()}`;

            // Check if already loaded by us or exists in DOM
            if (FontService.loadedFonts.has(fontId) || document.getElementById(fontId)) {
                return;
            }

            let styleContent = '';

            // Case 1: Google Font URL (CSS)
            if (font.url && (font.url.includes('googleapis.com') || font.source === 'google')) {
                const link = document.createElement('link');
                link.id = fontId;
                link.rel = 'stylesheet';
                link.href = font.url;
                document.head.appendChild(link);
                FontService.loadedFonts.add(fontId);
                return;
            }

            // Case 2: Direct URL (Custom Upload)
            // Prioritize specific formats if available in 'files'
            if (font.files && (font.files.woff2 || font.files.woff || font.files.ttf || font.files.otf)) {
                const sources = [];
                if (font.files.woff2) sources.push(`url('${font.files.woff2}') format('woff2')`);
                if (font.files.woff) sources.push(`url('${font.files.woff}') format('woff')`);
                if (font.files.ttf) sources.push(`url('${font.files.ttf}') format('truetype')`);
                if (font.files.otf) sources.push(`url('${font.files.otf}') format('opentype')`);

                styleContent = `
                    @font-face {
                        font-family: '${font.family}';
                        src: ${sources.join(', ')};
                        font-weight: normal;
                        font-style: normal;
                        font-display: swap;
                    }
                `;
            } else if (font.url) {
                // Fallback to single URL
                styleContent = `
                    @font-face {
                        font-family: '${font.family}';
                        src: url('${font.url}');
                    }
                `;
            }

            if (styleContent) {
                const style = document.createElement('style');
                style.id = fontId;
                style.innerHTML = styleContent;
                document.head.appendChild(style);
                FontService.loadedFonts.add(fontId);
            }
        });
    }
};

export default FontService;

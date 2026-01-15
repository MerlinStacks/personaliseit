/**
 * useExportLoader - Custom hook for loading order export data
 * 
 * Handles fetching order item data, fonts, and user inputs for the export renderer.
 * Extracted from ExportRenderer.js for modularity and testability.
 */
import { useEffect, useState, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import useFrontendStore from '../../frontend/store/useFrontendStore';
import FontService from '../../common/services/FontService';

/**
 * Loads order data and hydrates the store for export rendering
 * @param {string} orderId - WooCommerce order ID
 * @param {string} itemId - Order item ID
 * @returns {Object} Loading state and status
 */
const useExportLoader = (orderId, itemId) => {
    const [isLoading, setIsLoading] = useState(true);
    const [status, setStatus] = useState('Initializing...');

    // Store actions
    const setConfig = useFrontendStore((state) => state.setConfig);
    const setProductImage = useFrontendStore((state) => state.setProductImage);
    const setFonts = useFrontendStore((state) => state.setFonts);
    const setUserInputs = useFrontendStore((state) => state.setUserInputs);
    const setUserStyles = useFrontendStore((state) => state.setUserStyles);
    const addLayer = useFrontendStore((state) => state.addLayer);

    /**
     * Preloads image URLs to ensure they're available for canvas rendering
     * @param {string[]} urls - Array of image URLs
     */
    const preloadImages = useCallback(async (urls) => {
        if (urls.length === 0) return;

        setStatus('Loading images...');
        await Promise.all(urls.map(url =>
            new Promise(resolve => {
                const img = new Image();
                img.crossOrigin = 'anonymous';
                img.onload = img.onerror = resolve;
                img.src = url;
            })
        ));
    }, []);

    /**
     * Hydrates user inputs and styles from order data
     * @param {Object} data - Order data containing userInputs
     */
    const hydrateUserData = useCallback((data) => {
        if (!data.userInputs) return;

        if (data.userInputs.inputs) {
            setUserInputs(data.userInputs.inputs);
            setUserStyles(data.userInputs.styles || {});

            if (data.userInputs.embroideryColor) {
                useFrontendStore.getState().setEmbroideryColor(data.userInputs.embroideryColor);
            }

            if (data.userInputs.customLayers && data.config?.views?.[0]?.id) {
                data.userInputs.customLayers.forEach(l =>
                    addLayer(data.config.views[0].id, l)
                );
            }
        } else {
            setUserInputs(data.userInputs);
            setUserStyles({});
        }
    }, [setUserInputs, setUserStyles, addLayer]);

    useEffect(() => {
        const loadData = async () => {
            if (!orderId || !itemId) {
                setStatus('Error: Missing Order/Item ID');
                return;
            }

            try {
                // 1. Fetch and load fonts
                const fonts = await apiFetch({ path: '/personaliseit/v1/fonts' });
                setFonts(fonts);
                FontService.loadFontsIntoDom(fonts);
                await document.fonts.ready;

                // 2. Fetch order data
                const data = await apiFetch({
                    path: `/personaliseit/v1/order-item/${orderId}/${itemId}`
                });

                if (data.config) setConfig(data.config);
                if (data.productImage) setProductImage(data.productImage);

                // 3. Hydrate user data
                hydrateUserData(data);

                // 4. Preload images
                const inputs = data.userInputs?.inputs || data.userInputs || {};
                const imageUrls = Object.values(inputs).filter(v =>
                    typeof v === 'string' && (v.startsWith('http') || v.startsWith('/'))
                );
                await preloadImages(imageUrls);

                setIsLoading(false);
                setStatus('Ready');
            } catch (e) {
                setStatus('Error: ' + e.message);
                setIsLoading(false);
            }
        };

        loadData();
    }, [orderId, itemId, setFonts, setConfig, setProductImage, hydrateUserData, preloadImages]);

    return { isLoading, status, setStatus };
};

export default useExportLoader;

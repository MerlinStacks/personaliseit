import { useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import useStore from '../store/useStore';
import FontService from '../../common/services/FontService';

/**
 * useDesignerLogic
 * Encapsulates state and logic for the Designer component.
 */
const useDesignerLogic = (canvasRef) => {
    // Store Selectors
    const selectedProduct = useStore((state) => state.selectedProduct);
    const setSelectedProduct = useStore((state) => state.setSelectedProduct);
    const orderMode = useStore((state) => state.orderMode);
    const templateId = useStore((state) => state.templateId);

    // View State
    const views = useStore((state) => state.views);
    const setViews = useStore((state) => state.setViews);
    const setCurrentViewId = useStore((state) => state.setCurrentViewId);

    // Variation State
    const setVariations = useStore((state) => state.setVariations);
    const currentVariationId = useStore((state) => state.currentVariationId);
    const setCurrentVariationId = useStore((state) => state.setCurrentVariationId);

    // Config State
    const personalisationMethod = useStore((state) => state.personalisationMethod);
    const setPersonalisationMethod = useStore((state) => state.setPersonalisationMethod);
    const paletteMap = useStore((state) => state.paletteMap);
    const setPaletteMap = useStore((state) => state.setPaletteMap);
    const importConfig = useStore((state) => state.importConfig);
    const fetchSettings = useStore((state) => state.fetchSettings);

    // Layer Actions
    const selectedLayerId = useStore((state) => state.selectedLayerId);
    const duplicateLayer = useStore((state) => state.duplicateLayer);
    const removeLayer = useStore((state) => state.removeLayer);
    const undo = useStore((state) => state.undo);
    const redo = useStore((state) => state.redo);

    // Local State
    const [isSaving, setIsSaving] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [notices, setNotices] = useState([]);
    const [fonts, setFonts] = useState([]);

    // Mobile View State
    const [mobileView, setMobileView] = useState('canvas'); // 'left', 'canvas', 'right'

    // Tools State
    const [showGrid, setShowGrid] = useState(false);
    const [snapToGrid, setSnapToGrid] = useState(false);

    // Helpers
    const addNotice = useCallback((message, status = 'success') => {
        const id = Date.now();
        setNotices((prev) => [...prev, { id, content: message, status }]);
        setTimeout(() => setNotices((prev) => prev.filter((n) => n.id !== id)), 3000);
    }, []);

    const removeNotice = (id) => {
        setNotices((prev) => prev.filter((notice) => notice.id !== id));
    };

    // 1. Initial Setup
    useEffect(() => {
        fetchSettings();
    }, []);

    // 2. Data Loading
    useEffect(() => {
        const initData = async () => {
            // Fetch fonts
            try {
                const fontData = await FontService.getFonts();
                setFonts(fontData);
                FontService.loadFontsIntoDom(fontData);
            } catch (err) {
                console.error(err);
            }

            if (orderMode) {
                await loadOrderData();
            } else if (templateId) {
                await loadTemplateData();
            } else if (selectedProduct) {
                await loadConfig();
            }
        };
        initData();
    }, [selectedProduct?.id, orderMode?.orderId, templateId]);

    // 3. Logic: Load Order Data
    const loadOrderData = async () => {
        setIsLoading(true);
        try {
            const data = await apiFetch({
                path: `/personaliseit/v1/order-item/${orderMode.orderId}/${orderMode.itemId}`,
            });

            if (!selectedProduct) {
                setSelectedProduct({
                    id: data.product_id,
                    name: `Order #${orderMode.orderId}`,
                });
            }

            if (data.config) {
                const config = data.config;
                const userInputs = data.userInputs || {};
                const inputs = userInputs.inputs || userInputs;
                const styles = userInputs.styles || {};
                const customLayers = userInputs.customLayers || [];

                if (config.views) {
                    config.views.forEach((v) => {
                        v.layers.forEach((l) => {
                            if (inputs[l.id]) {
                                if (l.type === 'text') l.text = inputs[l.id];
                                if (l.type === 'image') l.image = inputs[l.id];
                            }
                            if (styles[l.id]) {
                                Object.assign(l, styles[l.id]);
                            }
                        });
                    });
                    if (customLayers.length > 0 && config.views.length > 0) {
                        config.views[0].layers.push(...customLayers);
                    }
                }
                importConfig(config);
            }
        } catch (error) {
            console.error(error);
            addNotice(__('Failed to load order data.', 'personaliseit'), 'error');
        } finally {
            setIsLoading(false);
        }
    };

    // 4. Logic: Load Template Data
    const loadTemplateData = async () => {
        setIsLoading(true);
        try {
            const post = await apiFetch({ path: `/wp/v2/personaliseit_tpl/${templateId}?context=edit` });
            let config;
            try {
                config = JSON.parse(post.content.raw);
            } catch (e) {
                config = post.content.raw;
            }
            if (typeof config === 'string') {
                try { config = JSON.parse(config); } catch (e) { /* ignore */ }
            }

            if (config) {
                importConfig(config);
            } else {
                addNotice(__('Template is empty.', 'personaliseit'), 'warning');
            }
        } catch (error) {
            console.error(error);
            addNotice(__('Failed to load template.', 'personaliseit'), 'error');
        } finally {
            setIsLoading(false);
        }
    };

    // 5. Logic: Load Product Config
    const loadConfig = async () => {
        setIsLoading(true);
        try {
            const product = await apiFetch({ path: `/wc/v3/products/${selectedProduct.id}` });

            // Variations
            if (product.type === 'variable') {
                const variationData = await apiFetch({ path: `/wc/v3/products/${selectedProduct.id}/variations` });
                const vars = variationData.map((v) => ({
                    id: v.id,
                    name: v.attributes.map((a) => a.option).join(' - ') || `Variation #${v.id}`,
                    image: v.image ? v.image.src : '',
                }));
                // Dedup
                const uniqueVars = [];
                const map = new Map();
                for (const item of vars) {
                    if (!map.has(item.id)) {
                        map.set(item.id, true);
                        uniqueVars.push(item);
                    }
                }
                setVariations(uniqueVars);
                if (uniqueVars.length > 0 && !currentVariationId) {
                    setCurrentVariationId(uniqueVars[0].id);
                }
            } else {
                setVariations([]);
                setCurrentVariationId(null);
            }

            // Meta Config
            const meta = product.meta_data.find((m) => m.key === '_personaliseit_config');
            if (meta && meta.value) {
                setPersonalisationMethod(meta.value.personalisationMethod || 'none');
                setPaletteMap(meta.value.paletteMap || {});

                let loadedViews = meta.value.views || [];
                if (loadedViews.length === 0) {
                    loadedViews = [{
                        id: 'view_default',
                        name: 'Front',
                        image: product.images[0]?.src || '',
                        layers: [],
                        variationImages: {},
                    }];
                }
                setViews(loadedViews);
                setCurrentViewId(loadedViews[0].id);
            } else {
                // Initialize Default
                setPersonalisationMethod('none');
                const initialView = {
                    id: 'view_default',
                    name: 'Front',
                    image: product.images[0]?.src || '',
                    layers: [],
                    variationImages: {},
                };
                setViews([initialView]);
                setCurrentViewId(initialView.id);
            }
        } catch (error) {
            console.error(error);
            addNotice(__('Failed to load configuration.', 'personaliseit'), 'error');
        } finally {
            setIsLoading(false);
        }
    };

    // 6. Logic: Save
    const handleSave = async () => {
        setIsSaving(true);
        try {
            const dataToSave = {
                views: views,
                personalisationMethod: personalisationMethod,
                paletteMap: paletteMap
            };

            if (templateId) {
                await apiFetch({
                    path: `/wp/v2/personaliseit_tpl/${templateId}`,
                    method: 'POST',
                    data: { content: JSON.stringify(dataToSave) }
                });
            } else {
                await apiFetch({
                    path: `/wc/v3/products/${selectedProduct.id}`,
                    method: 'PUT',
                    data: {
                        meta_data: [{ key: '_personaliseit_config', value: dataToSave }],
                    },
                });
            }
            addNotice(__('Configuration saved successfully!', 'personaliseit'), 'success');
        } catch (error) {
            console.error(error);
            addNotice(__('Failed to save configuration.', 'personaliseit'), 'error');
        } finally {
            setIsSaving(false);
        }
    };

    // 7. Keyboard Shortcuts (Memoized)
    useEffect(() => {
        const handleKeyDown = (e) => {
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName) || e.target.isContentEditable) return;

            // Undo: Ctrl+Z
            if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                undo();
                return;
            }
            // Redo: Ctrl+Shift+Z or Ctrl+Y
            if (((e.ctrlKey || e.metaKey) && e.shiftKey && e.key.toLowerCase() === 'z') ||
                ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y')) {
                e.preventDefault();
                redo();
                return;
            }
            // Duplicate: Ctrl+D
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'd') {
                e.preventDefault();
                if (selectedLayerId) duplicateLayer(selectedLayerId);
                return;
            }
            // Delete: Delete or Backspace
            if (e.key === 'Delete' || e.key === 'Backspace') {
                e.preventDefault();
                if (selectedLayerId) removeLayer(selectedLayerId);
                return;
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    }, [selectedLayerId, undo, redo, duplicateLayer, removeLayer]);

    return {
        // State
        selectedProduct,
        orderMode,
        templateId,
        views,
        isSaving,
        isLoading,
        notices,
        fonts,
        mobileView,
        setMobileView,
        showGrid,
        setShowGrid,
        snapToGrid,
        setSnapToGrid,

        // Computed
        exportConfig: useStore((state) => state.exportConfig),
        importConfig: useStore((state) => state.importConfig),

        // Actions
        addNotice,
        removeNotice,
        handleSave,
        setIsLoading
    };
};

export default useDesignerLogic;

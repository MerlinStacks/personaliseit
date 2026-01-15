/**
 * useExportActions - Custom hook for export action handlers
 * 
 * Provides export functionality for single-view and multi-view exports
 * in PNG, JPG, PDF, and SVG formats.
 */
import { useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import useFrontendStore from '../../frontend/store/useFrontendStore';
import { generateSVG } from '../utils/generateSVG';
import ExportService from '../../common/services/ExportService';

/**
 * Provides export action handlers bound to the current state
 * @param {Object} params - Hook parameters
 * @param {Object} params.exportStageRefs - Refs for each view's export canvas
 * @param {string} params.orderId - Order ID for filename
 * @param {string} params.itemId - Item ID for filename
 * @param {Function} params.setStatus - Status message setter
 * @param {Function} params.setIsExporting - Export state setter
 * @returns {Object} Export action handlers
 */
const useExportActions = ({ exportStageRefs, orderId, itemId, setStatus, setIsExporting }) => {
    const views = useFrontendStore((state) => state.views);
    const currentViewId = useFrontendStore((state) => state.currentViewId);
    const userInputs = useFrontendStore((state) => state.userInputs);
    const userStyles = useFrontendStore((state) => state.userStyles);
    const fonts = useFrontendStore((state) => state.fonts);

    /**
     * Generates a sanitized filename for exports
     * @param {string} viewName - Optional view name suffix
     * @returns {string} Sanitized filename base
     */
    const getFilename = useCallback((viewName = '') => {
        const base = `order_${orderId}_item_${itemId}`;
        return viewName ? `${base}_${viewName.toLowerCase().replace(/\s+/g, '_')}` : base;
    }, [orderId, itemId]);

    /**
     * Handles SVG export with text metrics calculation
     * @param {Object} view - View configuration
     * @param {string} filename - Base filename
     */
    const handleSVGExport = useCallback(async (view, filename) => {
        const state = useFrontendStore.getState();

        const mergedLayers = view.layers
            .filter(l => !l.excludeFromExport)
            .map(l => {
                const style = state.userStyles[l.id] || {};
                return {
                    ...l,
                    text: state.userInputs[l.id] || l.label,
                    image: (l.type === 'image' || l.type === 'clipart') ? state.userInputs[l.id] : null,
                    color: style.color || l.color,
                    fontFamily: style.fontFamily || l.fontFamily
                };
            });

        // Calculate text metrics for proper SVG sizing
        const textMetrics = {};
        const ctx = document.createElement('canvas').getContext('2d');
        mergedLayers.forEach(l => {
            if (l.type === 'text') {
                const fontSize = Number(l.fontSize) || 24;
                const fontFamily = (state.userStyles[l.id]?.fontFamily) || l.fontFamily || 'Arial';
                ctx.font = `${fontSize}px "${fontFamily}"`;

                const text = (state.userInputs[l.id] || l.label || '').toString();
                textMetrics[l.id] = {
                    lines: text.split('\n').map(line => {
                        const m = ctx.measureText(line);
                        return {
                            width: m.width,
                            ascent: m.actualBoundingBoxAscent,
                            descent: m.actualBoundingBoxDescent
                        };
                    })
                };
            }
        });

        const stageRef = exportStageRefs.current[currentViewId];
        const stage = stageRef?.current?.getStage();
        const scaleX = stage?.scaleX() || 1;
        const scaleY = stage?.scaleY() || 1;

        const svgString = await generateSVG({ layers: mergedLayers }, {
            width: (stage?.width() || 800) / scaleX,
            height: (stage?.height() || 800) / scaleY,
            fonts: fonts || [],
            textMetrics
        });

        const blob = new Blob([svgString], { type: 'image/svg+xml;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        ExportService.downloadURI(url, `${filename}.svg`);
        URL.revokeObjectURL(url);
    }, [exportStageRefs, currentViewId, fonts]);

    /**
     * Exports the currently selected view in the specified format
     * @param {string} format - Export format (png, jpg, pdf, svg)
     */
    const handleExportSingle = useCallback(async (format) => {
        const stageRef = exportStageRefs.current[currentViewId];
        if (!stageRef) return;

        setIsExporting(true);
        setStatus(__('Generating...', 'personaliseit'));

        try {
            const currentView = views.find(v => v.id === currentViewId);
            const filename = getFilename(currentView?.name);

            if (format === 'svg') {
                await handleSVGExport(currentView, filename);
            } else {
                await ExportService.downloadSingleView(stageRef, format, filename);
            }
            setStatus(__('Download complete', 'personaliseit'));
        } catch (err) {
            console.error('Export failed:', err);
            setStatus(__('Export failed:', 'personaliseit') + ' ' + err.message);
        } finally {
            setIsExporting(false);
        }
    }, [exportStageRefs, currentViewId, views, getFilename, handleSVGExport, setIsExporting, setStatus]);

    /**
     * Exports all views as a ZIP or multi-page PDF
     * @param {string} format - Export format (zip-png, zip-jpg, pdf)
     */
    const handleExportAll = useCallback(async (format) => {
        if (views.length === 0) return;

        setIsExporting(true);
        setStatus(__('Generating all views...', 'personaliseit'));

        try {
            // Wait for all canvases to be ready
            await new Promise(resolve => setTimeout(resolve, 500));

            const viewsData = views.map(view => {
                const stageRef = exportStageRefs.current[view.id];
                if (!stageRef?.current) return null;

                const stage = stageRef.current.getStage();
                const dataUrl = stage.toDataURL({
                    pixelRatio: 4,
                    mimeType: format === 'zip-jpg' ? 'image/jpeg' : 'image/png',
                    quality: 0.92
                });

                return {
                    name: view.name || view.id,
                    dataUrl,
                    width: stage.width(),
                    height: stage.height()
                };
            }).filter(Boolean);

            if (viewsData.length === 0) {
                throw new Error('No views available for export');
            }

            await ExportService.downloadAllViews(viewsData, format, getFilename('all_views'));
            setStatus(__('Download complete', 'personaliseit'));
        } catch (err) {
            console.error('Multi-view export failed:', err);
            setStatus(__('Export failed:', 'personaliseit') + ' ' + err.message);
        } finally {
            setIsExporting(false);
        }
    }, [views, exportStageRefs, getFilename, setIsExporting, setStatus]);

    return {
        handleExportSingle,
        handleExportAll,
        getFilename
    };
};

export default useExportActions;

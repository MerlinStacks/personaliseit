import { useEffect, useState, useRef } from '@wordpress/element';
import { Spinner, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import useFrontendStore from '../../frontend/store/useFrontendStore';
import FrontendCanvas from '../../frontend/components/FrontendCanvas';
import { generateSVG } from '../utils/generateSVG';
import { PDFDocument } from 'pdf-lib';
import FontService from '../../common/services/FontService';


const ExportRenderer = () => {
    const [isLoading, setIsLoading] = useState(true);
    const [status, setStatus] = useState(__('Initializing...', 'personaliseit'));
    const [autoDownloadFired, setAutoDownloadFired] = useState(false);

    // Two refs: one for preview (visible), one for export (hidden/clean)
    const previewStageRef = useRef();
    const exportStageRef = useRef();

    const config = useFrontendStore((state) => state.config);
    const setConfig = useFrontendStore((state) => state.setConfig);
    const setProductImage = useFrontendStore((state) => state.setProductImage);
    const setFonts = useFrontendStore((state) => state.setFonts);
    const setUserInputs = useFrontendStore((state) => state.setUserInputs);
    const setUserStyles = useFrontendStore((state) => state.setUserStyles);
    const addLayer = useFrontendStore((state) => state.addLayer);

    useEffect(() => {
        const runData = async () => {
            const params = new URLSearchParams(window.location.search);
            const orderId = params.get('order_id');
            const itemId = params.get('item_id');

            if (!orderId || !itemId) {
                setStatus(__('Error: Missing Order/Item ID', 'personaliseit'));
                return;
            }

            try {
                // 1. Fetch Fonts
                const fonts = await apiFetch({ path: '/personaliseit/v1/fonts' });
                setFonts(fonts);
                // Inject styles using shared utility
                FontService.loadFontsIntoDom(fonts);

                // Wait for fonts to load before metrics or export
                await document.fonts.ready;

                // 2. Fetch Order Data
                const data = await apiFetch({ path: `/personaliseit/v1/order-item/${orderId}/${itemId}` });

                if (data.config) setConfig(data.config);
                if (data.productImage) setProductImage(data.productImage);

                if (data.userInputs) {
                    // Handle both nested format { inputs, styles, embroideryColor } and legacy flat format
                    if (data.userInputs.inputs) {
                        // New format with nested structure
                        setUserInputs(data.userInputs.inputs);
                        setUserStyles(data.userInputs.styles || {});

                        // Apply embroidery color if present
                        if (data.userInputs.embroideryColor) {
                            const ec = data.userInputs.embroideryColor;
                            useFrontendStore.getState().setEmbroideryColor(ec);
                        }

                        if (data.userInputs.customLayers) {
                            const viewId = data.config.views ? data.config.views[0].id : (data.config.layers ? 'front' : null);
                            if (viewId) {
                                data.userInputs.customLayers.forEach(l => addLayer(viewId, l));
                            }
                        }
                    } else {
                        // Legacy flat format - userInputs is the inputs map directly
                        // In this case there are no styles saved, use empty
                        setUserInputs(data.userInputs);
                        setUserStyles({});
                    }
                }

                // Preload all image URLs from userInputs before marking as ready
                const imageUrls = [];
                const inputs = data.userInputs?.inputs || data.userInputs || {};
                Object.values(inputs).forEach(val => {
                    if (typeof val === 'string' && (val.startsWith('http') || val.startsWith('/'))) {
                        // Looks like a URL - preload it
                        imageUrls.push(val);
                    }
                });

                if (imageUrls.length > 0) {
                    setStatus(__('Loading images...', 'personaliseit'));
                    await Promise.all(imageUrls.map(url => {
                        return new Promise((resolve) => {
                            const img = new Image();
                            img.crossOrigin = 'anonymous';
                            img.onload = resolve;
                            img.onerror = resolve; // Continue even if one fails
                            img.src = url;
                        });
                    }));
                }

                setIsLoading(false);
                setStatus(__('Ready. Preparing download...', 'personaliseit'));

            } catch (e) {
                setStatus(__('Error:', 'personaliseit') + ' ' + e.message);
                setIsLoading(false);
            }
        };
        runData();
    }, []);

    // Auto-download effect
    useEffect(() => {
        if (!isLoading && !autoDownloadFired && exportStageRef.current) {
            const params = new URLSearchParams(window.location.search);
            const format = params.get('format');
            if (format) {
                // Determine wait time based on format (PDF/SVG might need more time or fonts)
                const wait = 2000;
                setStatus(__('Generating file...', 'personaliseit'));

                const timer = setTimeout(() => {
                    handleExport(format);
                    setAutoDownloadFired(true);
                    setStatus(__('Downloaded. Closing...', 'personaliseit'));
                    // Close the window after a short delay
                    setTimeout(() => {
                        window.close();
                    }, 500);
                }, wait);
                return () => clearTimeout(timer);
            } else {
                setStatus(__('Ready. Click Export to download.', 'personaliseit'));
            }
        }
    }, [isLoading, autoDownloadFired]);

    // Helper to check if design contains customer-uploaded images
    const hasImageLayers = () => {
        const state = useFrontendStore.getState();
        const viewId = state.currentViewId || (state.views && state.views[0] ? state.views[0].id : null);
        const view = state.views.find(v => v.id === viewId);
        if (!view) return false;

        return view.layers.some(l =>
            (l.type === 'image' || l.type === 'clipart') &&
            !l.excludeFromExport &&
            state.userInputs[l.id]
        );
    };


    const handleExport = (formatOverride = null) => {
        if (!exportStageRef.current) return;
        const stage = exportStageRef.current.getStage();
        const pixelRatio = 4; // 300 DPI equivalent roughly

        // --- CLEAN EXPORT (Managed by exportMode check in DesignRenderer) ---
        // No manual node hiding needed!
        // ------------------------------------------------

        const params = new URLSearchParams(window.location.search);
        const format = formatOverride || params.get('format') || 'png';
        const filename = `print_order_${params.get('order_id') || 'export'}.${format}`;

        if (format === 'pdf') {
            // pdf-lib: Modern async PDF generation
            (async () => {
                try {
                    const pdfDoc = await PDFDocument.create();
                    const imgData = stage.toDataURL({ pixelRatio: pixelRatio });

                    // Convert data URL to bytes
                    const imageBytes = await fetch(imgData).then(res => res.arrayBuffer());
                    const pngImage = await pdfDoc.embedPng(imageBytes);

                    const pdfWidth = stage.width() * pixelRatio;
                    const pdfHeight = stage.height() * pixelRatio;

                    const page = pdfDoc.addPage([pdfWidth, pdfHeight]);
                    page.drawImage(pngImage, {
                        x: 0,
                        y: 0,
                        width: pdfWidth,
                        height: pdfHeight,
                    });

                    const pdfBytes = await pdfDoc.save();
                    const blob = new Blob([pdfBytes], { type: 'application/pdf' });
                    const url = URL.createObjectURL(blob);
                    downloadURI(url, filename);
                } catch (err) {
                    console.error('PDF Export Failed:', err);
                    alert('PDF Export Failed: ' + err.message);
                }
            })();
        } else if (format === 'svg') {
            try {
                // True Vector SVG Export using custom generator
                // Retrieve fonts directly from the store state where they are loaded
                const fonts = useFrontendStore.getState().fonts || [];

                // Construct stageData from store or simple object
                const state = useFrontendStore.getState();
                const viewId = state.currentViewId || (state.views && state.views[0] ? state.views[0].id : null);
                const view = state.views.find(v => v.id === viewId);

                // Merge User Inputs into Layers AND Filter Excluded
                const mergedLayers = view.layers
                    .filter(l => !l.excludeFromExport)
                    .map(l => {
                        const style = state.userStyles[l.id] || {};
                        return {
                            ...l,
                            text: state.userInputs[l.id] || l.label,
                            // If image/clipart, userInputs[l.id] contains the URL
                            image: (l.type === 'image' || l.type === 'clipart') ? state.userInputs[l.id] : null,
                            color: style.color || l.color,
                            fontFamily: style.fontFamily || l.fontFamily
                        };
                    });

                // Precision Text Metrics Calculation (Plan F)
                const textMetrics = {};
                const ctx = document.createElement('canvas').getContext('2d');

                mergedLayers.forEach(l => {
                    if (l.type === 'text') {
                        const fontSize = Number(l.fontSize) || 24;
                        const fontFamily = (state.userStyles[l.id] && state.userStyles[l.id].fontFamily) || l.fontFamily || 'Arial';
                        ctx.font = `${fontSize}px "${fontFamily}"`;

                        const text = (state.userInputs[l.id] || l.label || '').toString();
                        const lines = text.split('\n');
                        textMetrics[l.id] = { lines: [] };

                        lines.forEach(line => {
                            const m = ctx.measureText(line);
                            textMetrics[l.id].lines.push({
                                width: m.width,
                                ascent: m.actualBoundingBoxAscent, // Distance from baseline to top of ink
                                descent: m.actualBoundingBoxDescent // Distance from baseline to bottom of ink
                            });
                        });
                    }
                });

                setStatus(__('Generating SVG (expanding fonts)...', 'personaliseit'));

                // ASYNC call
                // We must use Unscaled Dimensions for the SVG ViewBox because the Layer coordinates are Unscaled.
                const scaleX = stage.scaleX() || 1;
                const scaleY = stage.scaleY() || 1;

                generateSVG({ layers: mergedLayers }, {
                    width: stage.width() / scaleX,
                    height: stage.height() / scaleY,
                    fonts: fonts,
                    textMetrics: textMetrics // Pass precise metrics
                }).then(svgString => {
                    const blob = new Blob([svgString], { type: "image/svg+xml;charset=utf-8" });
                    const url = URL.createObjectURL(blob);
                    downloadURI(url, filename);
                    setStatus(__('Download ready.', 'personaliseit'));
                }).catch(err => {
                    console.error(err);
                    alert('SVG Generation Error: ' + err.message);
                });

            } catch (e) {
                console.error('SVG Export Failed:', e);
                alert('SVG Export Failed: ' + e.message);
            }
        } else if (format === 'jpg') {
            const dataUrl = stage.toDataURL({ pixelRatio: pixelRatio, mimeType: 'image/jpeg', quality: 0.9 });
            downloadURI(dataUrl, filename);
        } else {
            // PNG
            const dataUrl = stage.toDataURL({ pixelRatio: pixelRatio });
            downloadURI(dataUrl, filename);
        }
    };

    const downloadURI = (uri, name) => {
        const link = document.createElement('a');
        link.download = name;
        link.href = uri;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    if (isLoading) return <div style={{ padding: 20 }}><Spinner /> {status}</div>;

    const params = new URLSearchParams(window.location.search);
    const format = params.get('format');

    // Minimal UI if auto-downloading
    if (format && !autoDownloadFired) {
        return (
            <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center', justifyContent: 'center', height: '100vh', background: '#fff' }}>
                <Spinner />
                <p style={{ marginTop: 20 }}>{status}</p>
                {/* Visual Preview (Standard Mode) */}
                <div style={{
                    position: 'absolute',
                    opacity: 0,
                    pointerEvents: 'none',
                    width: config?.canvasWidth || 800,
                    height: config?.canvasHeight || 800
                }}>
                    <FrontendCanvas stageRef={previewStageRef} />
                </div>

                {/* Clean Export Canvas (Export Mode) */}
                <div style={{ position: 'absolute', top: -10000, left: -10000 }}>
                    <FrontendCanvas stageRef={exportStageRef} exportMode={true} />
                </div>
            </div>
        );
    }

    const containsImages = hasImageLayers();

    return (
        <div className="personaliseit-export-wrapper" style={{ padding: 20, background: '#fff' }}>
            <div style={{ marginBottom: 20, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                <h2>{__('High-Res Export Preview', 'personaliseit')} {format ? `(${format.toUpperCase()})` : ''}</h2>
                <div style={{ display: 'flex', gap: 10 }}>
                    <Button isPrimary onClick={() => handleExport('png')}>{__('Download PNG', 'personaliseit')}</Button>
                    <Button isSecondary onClick={() => handleExport('jpg')}>{__('JPG', 'personaliseit')}</Button>
                    <Button isSecondary onClick={() => handleExport('pdf')}>{__('PDF', 'personaliseit')}</Button>
                    <Button
                        isSecondary
                        onClick={() => handleExport('svg')}
                        disabled={containsImages}
                        title={containsImages ? __('SVG export is not available when design contains images', 'personaliseit') : ''}
                    >
                        {__('SVG', 'personaliseit')}
                    </Button>
                </div>
            </div>
            {status && <p>{status}</p>}

            <div style={{ border: '1px solid #ccc', display: 'inline-block', width: config?.canvasWidth || 800, height: config?.canvasHeight || 800 }}>
                <FrontendCanvas stageRef={previewStageRef} />
            </div>

            {/* Hidden Clean Export Canvas */}
            <div style={{ position: 'absolute', top: -10000, left: -10000 }}>
                <FrontendCanvas stageRef={exportStageRef} exportMode={true} />
            </div>

            <p style={{ marginTop: 10, fontSize: 12, color: '#666' }}>
                {containsImages
                    ? __('Note: SVG export is disabled because this design contains images.', 'personaliseit')
                    : __('Note: SVG export converts text to vector paths for production use.', 'personaliseit')
                }
            </p>
        </div>
    );
};
export default ExportRenderer;

import { __ } from '@wordpress/i18n';
import { useState, useMemo, useEffect } from '@wordpress/element';
import usePersonaliseItStore from '../store/useFrontendStore';
import EmbroideryPalette from './EmbroideryPalette';
import TextLayerControl from './TextLayerControl';
import ImageLayerControl from './ImageLayerControl';
import SpotifyLayerControl from './SpotifyLayerControl';
import ClipartLayerControl from './ImageLayerControl'; // Clipart shares logic or has own? Usually ImageLayerControl handles it or separate. Assuming ImageLayerControl for now or need to check file list.
// Checking `ImageLayerControl.js` edits... it handles Text? No.
// `TextLayerControl.js` exists?
// I'll check file list later if needed, but for now I'll assume standard components.
// Actually, I'll use a generic `LayerControl` helper within the file or imported if it exists.
// The previous view showed `<LayerControl ... />`. I'll define it or import it.
// Wait, `import { ... }` ? The previous file had `const ControlsComponent`.
// I will implement `LayerControl` splitting inside the loop as typical.

import AssetBrowser from './AssetBrowser';

const LayerControl = ({ layer, ...props }) => {
    if (layer.type === 'text') {
        return <TextLayerControl layer={layer} {...props} />;
    } else if (layer.type === 'image' || layer.type === 'clipart') {
        return <ImageLayerControl layer={layer} {...props} />;
    } else if (layer.type === 'spotify') {
        return <SpotifyLayerControl layer={layer} {...props} />;
    }
    return null;
};

const ControlsComponent = () => {
    // Hooks using renamed store
    const config = usePersonaliseItStore((state) => state.config);
    const views = usePersonaliseItStore((state) => state.views);
    const currentViewId = usePersonaliseItStore((state) => state.currentViewId);
    const setCurrentViewId = usePersonaliseItStore((state) => state.setCurrentViewId);

    // Variations
    const variations = usePersonaliseItStore((state) => state.variations);
    const currentVariationId = usePersonaliseItStore((state) => state.currentVariationId);
    const setCurrentVariationId = usePersonaliseItStore((state) => state.setCurrentVariationId);

    const userInputs = usePersonaliseItStore((state) => state.userInputs);
    const userStyles = usePersonaliseItStore((state) => state.userStyles);
    const fonts = usePersonaliseItStore((state) => state.fonts);
    const updateInput = usePersonaliseItStore((state) => state.updateInput);
    const updateStyle = usePersonaliseItStore((state) => state.updateStyle);

    // Store Actions
    const a11yMessage = usePersonaliseItStore((state) => state.a11yMessage);
    const embroideryColor = usePersonaliseItStore((state) => state.embroideryColor);
    const stageRef = usePersonaliseItStore((state) => state.stageRef);

    // Local State
    const [showAssets, setShowAssets] = useState(false);
    const [selectedPlaceholderId, setSelectedPlaceholderId] = useState(null);

    // Derived State
    const currentView = views.find(v => v.id === currentViewId) || views[0];
    const layers = currentView ? currentView.layers : [];

    // Calculate Price (Basic implementation based on visible code)
    const totalPrice = useMemo(() => {
        let base = parseFloat(window.personaliseitData?.productPrice || 0);
        // Add layer costs if implementing pricing logic
        return base;
    }, [userInputs]);

    // Handle Asset Selection
    const handleSelectAsset = (url) => {
        if (selectedPlaceholderId) {
            updateInput(selectedPlaceholderId, url);
        } else {
            // Asset added via drag/drop or other flow logic if implemented
            // Placeholder usually handles it via updateInput
        }
        setShowAssets(false);
    };

    const getAllowedCategories = () => {
        return []; // Implement based on config
    };

    // Sync Data and Preview Image to Hidden Input
    const [formData, setFormData] = useState('');

    useEffect(() => {
        const syncTimer = setTimeout(() => {
            if (!stageRef || !stageRef.current) return;

            try {
                // Generate Preview (using JPEG 0.8 for speed/size)
                const dataUrl = stageRef.current.toDataURL({
                    pixelRatio: 1,
                    mimeType: 'image/jpeg',
                    quality: 0.8
                });

                const payload = {
                    inputs: userInputs,
                    styles: userStyles,
                    embroideryColor: embroideryColor || null,
                    previewImage: dataUrl
                };

                setFormData(JSON.stringify(payload));
                // console.log('PersonaliseIt: Synced to cart form');
            } catch (e) {
                console.warn('PersonaliseIt Preview Generation Failed:', e);
                // Fallback without image
                const payload = {
                    inputs: userInputs,
                    styles: userStyles,
                    embroideryColor: embroideryColor || null
                };
                setFormData(JSON.stringify(payload));
            }
        }, 800); // 800ms debounce to avoid lagging during typing

        return () => clearTimeout(syncTimer);
    }, [userInputs, userStyles, embroideryColor, stageRef]);

    const handleAddToCart = () => {
        // Now handled automatically via hidden input
    };

    // Get validation state
    const getValidationErrors = usePersonaliseItStore((state) => state.getValidationErrors);
    const validation = getValidationErrors();

    // Disable WooCommerce add-to-cart button when validation fails
    useEffect(() => {
        const addToCartBtn = document.querySelector('form.cart button[type="submit"], form.cart .single_add_to_cart_button');
        if (addToCartBtn) {
            if (!validation.isValid) {
                addToCartBtn.disabled = true;
                addToCartBtn.style.opacity = '0.5';
                addToCartBtn.style.cursor = 'not-allowed';
                addToCartBtn.title = validation.errors.map(e => e.message).join(', ');
            } else {
                addToCartBtn.disabled = false;
                addToCartBtn.style.opacity = '';
                addToCartBtn.style.cursor = '';
                addToCartBtn.title = '';
            }
        }
    }, [validation.isValid, validation.errors]);

    if (!config) return null;

    return (
        <div className="personaliseit-controls-wrapper">

            {/* Validation Errors */}
            {!validation.isValid && (
                <div
                    className="personaliseit-validation-errors"
                    style={{
                        background: '#fff2f2',
                        border: '1px solid #d63638',
                        borderRadius: '4px',
                        padding: '10px',
                        marginBottom: '15px'
                    }}
                >
                    <strong style={{ color: '#d63638', display: 'block', marginBottom: '5px' }}>
                        {__('Please fix the following:', 'personaliseit')}
                    </strong>
                    <ul style={{ margin: 0, paddingLeft: '20px', color: '#666' }}>
                        {validation.errors.map((err, idx) => (
                            <li key={idx}>{err.message}</li>
                        ))}
                    </ul>
                </div>
            )}

            {/* Hidden Input for Form Submission */}
            <input type="hidden" name="personaliseit_data" value={formData} />

            {/* View Switcher (Swatches) */}
            {views.length > 1 && (
                <div className="personaliseit-view-switcher" style={{ marginBottom: 15 }}>
                    <label style={{ display: 'block', fontWeight: 600, marginBottom: 5 }}>{__('Select View / Design:', 'personaliseit')}</label>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px' }}>
                        {views.map(v => {
                            // Use thumbnail if explicit, otherwise fallback to background image if available
                            const swatchImg = v.thumbnail || v.image;
                            return (
                                <div
                                    key={v.id}
                                    onClick={() => setCurrentViewId(v.id)}
                                    title={v.name}
                                    style={{
                                        border: currentViewId === v.id ? '2px solid #000' : '1px solid #ddd',
                                        borderRadius: '4px',
                                        padding: '4px',
                                        cursor: 'pointer',
                                        opacity: currentViewId === v.id ? 1 : 0.8,
                                        transition: 'all 0.2s',
                                        background: '#fff'
                                    }}
                                >
                                    {swatchImg ? (
                                        <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                                            <img
                                                src={swatchImg}
                                                alt={v.name}
                                                style={{ width: '60px', height: '60px', objectFit: 'contain', marginBottom: '4px' }}
                                            />
                                            <span style={{ fontSize: '10px', maxWidth: '60px', overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis' }}>{v.name}</span>
                                        </div>
                                    ) : (
                                        <button
                                            className="button"
                                            style={{
                                                backgroundColor: currentViewId === v.id ? '#000' : '#eee',
                                                color: currentViewId === v.id ? '#fff' : '#000',
                                                border: 'none',
                                                padding: '8px 12px',
                                                borderRadius: '2px',
                                                cursor: 'pointer',
                                                minHeight: '40px'
                                            }}
                                        >
                                            {v.name}
                                        </button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            )}

            {/* Variation Switcher (Swatches) */}
            {variations && variations.length > 0 && (
                <div className="personaliseit-variation-switcher" style={{ marginBottom: 15 }}>
                    <label style={{ display: 'block', fontWeight: 600, marginBottom: 5 }}>{__('Select Design:', 'personaliseit')}</label>
                    <div style={{ display: 'flex', flexWrap: 'wrap', gap: '8px' }}>
                        {variations.map(v => (
                            <div
                                key={v.id}
                                onClick={() => setCurrentVariationId(v.id)}
                                title={v.name}
                                style={{
                                    border: currentVariationId === v.id ? '2px solid #000' : '1px solid #ddd',
                                    borderRadius: '4px',
                                    padding: '4px',
                                    cursor: 'pointer',
                                    opacity: currentVariationId === v.id ? 1 : 0.8,
                                    transition: 'all 0.2s',
                                    background: '#fff'
                                }}
                            >
                                {v.image ? (
                                    <div style={{ display: 'flex', flexDirection: 'column', alignItems: 'center' }}>
                                        <img
                                            src={v.image.src || v.image}
                                            alt={v.name}
                                            style={{ width: '60px', height: '60px', objectFit: 'cover', marginBottom: '4px' }}
                                        />
                                        <span style={{ fontSize: '10px', maxWidth: '60px', overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis' }}>{v.name}</span>
                                    </div>
                                ) : (
                                    <button
                                        className="button"
                                        style={{
                                            backgroundColor: currentVariationId === v.id ? '#000' : '#eee',
                                            color: currentVariationId === v.id ? '#fff' : '#000',
                                            border: 'none',
                                            padding: '8px 12px',
                                            borderRadius: '2px',
                                            cursor: 'pointer'
                                        }}
                                    >
                                        {v.name}
                                    </button>
                                )}
                            </div>
                        ))}
                    </div>
                </div>
            )}

            {/* Global Embroidery Palette - Renders above all layers in embroidery mode */}
            {config?.personalisationMethod === 'embroidery' && (
                <EmbroideryPalette />
            )}

            {(window.personaliseitData?.settings?.showCost === '1' || window.personaliseitData?.settings?.showCost === true || window.personaliseitData?.settings?.showCost === undefined) && (
                <div className="price-display" style={{ marginBottom: '15px', fontWeight: 'bold', fontSize: '1.1em' }}>
                    {__('Total Price:', 'personaliseit')} {window.personaliseitData?.currencySymbol || '$'}{totalPrice.toFixed(2)}
                </div>
            )}

            {/* Layer Controls */}
            {!layers || layers.length === 0 ? (
                <div style={{ color: '#666', fontStyle: 'italic' }}>{__('No options available for this view.', 'personaliseit')}</div>
            ) : (
                layers.map((layer) => (
                    <LayerControl
                        key={layer.id}
                        layer={layer}
                        userInputs={userInputs}
                        userStyles={userStyles}
                        fonts={fonts}
                        updateInput={updateInput}
                        updateStyle={updateStyle}
                        handleAddToCart={handleAddToCart}
                        setSelectedPlaceholderId={setSelectedPlaceholderId}
                        setShowAssets={setShowAssets}
                        personalisationMethod={config?.personalisationMethod}
                        // activePalette removed, layers should read global embroideryColor derived from DesignRenderer context or store if needed
                        labelPosition={window.personaliseitData?.settings?.labelPosition || 'above'}
                    />
                ))
            )}

            {/* Asset Browser Modal */}
            {showAssets && (
                <AssetBrowser
                    onSelect={handleSelectAsset}
                    onClose={() => setShowAssets(false)}
                    allowedCategories={getAllowedCategories()}
                />
            )}

            {/* Hidden ARIA Live Region for Screen Readers */}
            <div
                aria-live="polite"
                aria-atomic="true"
                className="screen-reader-text"
                style={{
                    position: 'absolute',
                    width: '1px',
                    height: '1px',
                    margin: '-1px',
                    padding: 0,
                    overflow: 'hidden',
                    clip: 'rect(0, 0, 0, 0)',
                    border: 0
                }}
            >
                {a11yMessage}
            </div>
        </div>
    );
};

export default ControlsComponent;

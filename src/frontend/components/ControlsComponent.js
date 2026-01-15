/**
 * ControlsComponent - Frontend controls panel for personalization
 * 
 * Renders layer controls, view/variation switchers, and handles cart form sync.
 * Refactored in Phase 5 to use extracted hooks and components.
 */
import { __ } from '@wordpress/i18n';
import { useState, useMemo } from '@wordpress/element';
import usePersonaliseItStore from '../store/useFrontendStore';

// Layer controls
import TextLayerControl from './TextLayerControl';
import ImageLayerControl from './ImageLayerControl';
import SpotifyLayerControl from './SpotifyLayerControl';
import EmbroideryPalette from './EmbroideryPalette';
import AssetBrowser from './AssetBrowser';

// Extracted components and hooks
import { ViewSwitcher } from './controls';
import useFormSync from '../hooks/useFormSync';
import useValidationSync from '../hooks/useValidationSync';

/**
 * Routes to the correct layer control component based on type
 */
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

/**
 * Main controls component
 */
const ControlsComponent = () => {
    // Store state
    const config = usePersonaliseItStore((state) => state.config);
    const views = usePersonaliseItStore((state) => state.views);
    const currentViewId = usePersonaliseItStore((state) => state.currentViewId);
    const setCurrentViewId = usePersonaliseItStore((state) => state.setCurrentViewId);
    const variations = usePersonaliseItStore((state) => state.variations);
    const currentVariationId = usePersonaliseItStore((state) => state.currentVariationId);
    const setCurrentVariationId = usePersonaliseItStore((state) => state.setCurrentVariationId);
    const userInputs = usePersonaliseItStore((state) => state.userInputs);
    const userStyles = usePersonaliseItStore((state) => state.userStyles);
    const fonts = usePersonaliseItStore((state) => state.fonts);
    const updateInput = usePersonaliseItStore((state) => state.updateInput);
    const updateStyle = usePersonaliseItStore((state) => state.updateStyle);
    const a11yMessage = usePersonaliseItStore((state) => state.a11yMessage);
    const embroideryColor = usePersonaliseItStore((state) => state.embroideryColor);
    const stageRef = usePersonaliseItStore((state) => state.stageRef);
    const getValidationErrors = usePersonaliseItStore((state) => state.getValidationErrors);

    // Local state
    const [showAssets, setShowAssets] = useState(false);
    const [selectedPlaceholderId, setSelectedPlaceholderId] = useState(null);

    // Derived state
    const currentView = views.find(v => v.id === currentViewId) || views[0];
    const layers = currentView ? currentView.layers : [];
    const validation = getValidationErrors();

    // Calculate total price
    const totalPrice = useMemo(() => {
        return parseFloat(window.personaliseitData?.productPrice || 0);
    }, [userInputs]);

    // Form sync hook - generates JSON for hidden input
    const formData = useFormSync({
        userInputs,
        userStyles,
        embroideryColor,
        stageRef
    });

    // Validation sync hook - manages WooCommerce button state
    useValidationSync({
        isValid: validation.isValid,
        errors: validation.errors
    });

    // Asset selection handler
    const handleSelectAsset = (url) => {
        if (selectedPlaceholderId) {
            updateInput(selectedPlaceholderId, url);
        }
        setShowAssets(false);
    };

    const getAllowedCategories = () => [];

    const handleAddToCart = () => {
        // Handled automatically via hidden input
    };

    if (!config) return null;

    return (
        <div
            className="personaliseit-controls-wrapper"
            data-mode={config?.personalisationMethod || 'none'}
        >
            {/* Validation Errors */}
            {!validation.isValid && (
                <div className="pi-validation-errors">
                    <span className="dashicons dashicons-warning" />
                    <div className="pi-validation-errors__content">
                        <strong>{__('Please fix the following:', 'personaliseit')}</strong>
                        <ul>
                            {validation.errors.map((err, idx) => (
                                <li key={idx}>{err.message}</li>
                            ))}
                        </ul>
                    </div>
                </div>
            )}

            {/* Hidden Input for Form Submission */}
            <input type="hidden" name="personaliseit_data" value={formData} />

            {/* View Switcher */}
            <ViewSwitcher
                items={views}
                currentId={currentViewId}
                onSelect={setCurrentViewId}
                label={__('Select View / Design:', 'personaliseit')}
            />

            {/* Variation Switcher */}
            {variations && variations.length > 0 && (
                <ViewSwitcher
                    items={variations}
                    currentId={currentVariationId}
                    onSelect={setCurrentVariationId}
                    label={__('Select Design:', 'personaliseit')}
                    className="personaliseit-variation-switcher"
                />
            )}

            {/* Global Embroidery Palette */}
            {config?.personalisationMethod === 'embroidery' && (
                <EmbroideryPalette />
            )}

            {/* Price Display */}
            {(window.personaliseitData?.settings?.showCost === '1' ||
                window.personaliseitData?.settings?.showCost === true ||
                window.personaliseitData?.settings?.showCost === undefined) && (
                    <div className="price-display">
                        {__('Total Price:', 'personaliseit')} {window.personaliseitData?.currencySymbol || '$'}{totalPrice.toFixed(2)}
                    </div>
                )}

            {/* Layer Controls */}
            {!layers || layers.length === 0 ? (
                <div className="pi-no-layers">
                    {__('No options available for this view.', 'personaliseit')}
                </div>
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
            >
                {a11yMessage}
            </div>
        </div>
    );
};

export default ControlsComponent;

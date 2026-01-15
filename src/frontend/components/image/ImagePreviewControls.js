/**
 * ImagePreviewControls - Controls for an uploaded image
 * 
 * Renders filter, crop, and remove controls for an active image.
 * 
 * @module ImagePreviewControls
 */
import { __ } from '@wordpress/i18n';

/**
 * Preview and control panel for uploaded images
 * @param {Object} props - Component props
 * @param {Object} props.layer - Layer configuration
 * @param {Object} props.userInputs - User input values
 * @param {Object} props.userStyles - User style values
 * @param {Function} props.updateInput - Update input callback
 * @param {Function} props.updateStyle - Update style callback
 * @param {Function} props.handleAddToCart - Sync cart callback
 * @param {string} props.personalisationMethod - Current mode
 */
const ImagePreviewControls = ({
    layer,
    userInputs,
    userStyles,
    updateInput,
    updateStyle,
    handleAddToCart,
    personalisationMethod
}) => {
    const imageUrl = userInputs[layer.id];
    const currentFilter = userStyles[layer.id]?.filter || 'none';
    const currentMask = userStyles[layer.id]?.maskShape || 'none';

    /**
     * Handle image removal
     */
    const handleRemove = () => {
        updateInput(layer.id, '');
        updateStyle(layer.id, { filter: 'none' });
        handleAddToCart();
    };

    /**
     * Handle filter change
     */
    const handleFilterChange = (e) => {
        updateStyle(layer.id, { filter: e.target.value });
        handleAddToCart();
    };

    /**
     * Handle mask shape change
     */
    const handleMaskChange = (e) => {
        updateStyle(layer.id, { maskShape: e.target.value });
        handleAddToCart();
    };

    return (
        <div className="pi-flex-row pi-align-start">
            {/* Left: Compact Preview */}
            <div className="pi-compact-preview">
                {imageUrl && (
                    <img
                        src={imageUrl}
                        alt={__('Preview', 'personaliseit')}
                    />
                )}
            </div>

            {/* Right: Controls Stack */}
            <div className="pi-flex-col pi-flex-1">
                <div className="pi-flex-row pi-between">
                    <label className="pi-label-bold">
                        {layer.label || __('Image', 'personaliseit')}
                    </label>
                    <button
                        type="button"
                        className="pi-btn danger small"
                        onClick={handleRemove}
                        title={__('Remove Image', 'personaliseit')}
                        aria-label={__('Remove Image', 'personaliseit')}
                    >
                        <span className="dashicons dashicons-trash" aria-hidden="true"></span>
                    </button>
                </div>

                {/* Filter & Crop - Hidden in embroidery mode */}
                {personalisationMethod !== 'embroidery' && (
                    <div className="pi-flex-row">
                        <select
                            className="pi-modern-select pi-flex-1"
                            onChange={handleFilterChange}
                            value={currentFilter}
                            title={__('Apply Filter', 'personaliseit')}
                            aria-label={__('Image filter', 'personaliseit')}
                        >
                            <option value="none">{__('No Filter', 'personaliseit')}</option>
                            <option value="grayscale">{__('Greyscale', 'personaliseit')}</option>
                            <option value="sepia">{__('Sepia', 'personaliseit')}</option>
                            <option value="brightness">{__('Brighten', 'personaliseit')}</option>
                            <option value="contrast">{__('High Contrast', 'personaliseit')}</option>
                        </select>

                        <select
                            className="pi-modern-select pi-flex-1"
                            onChange={handleMaskChange}
                            value={currentMask}
                            title={__('Crop Shape', 'personaliseit')}
                            aria-label={__('Crop shape', 'personaliseit')}
                        >
                            <option value="none">{__('No Crop', 'personaliseit')}</option>
                            <option value="circle">{__('Circle', 'personaliseit')}</option>
                            <option value="heart">{__('Heart', 'personaliseit')}</option>
                            <option value="star">{__('Star', 'personaliseit')}</option>
                        </select>
                    </div>
                )}
            </div>
        </div>
    );
};

export default ImagePreviewControls;

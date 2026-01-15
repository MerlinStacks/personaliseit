/**
 * AiStylePanel - AI style transfer UI
 * 
 * Style selection and transfer controls for existing images.
 * 
 * @module AiStylePanel
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ProgressBar from '../../../common/components/ProgressBar';

/**
 * AI Style transfer panel component
 * @param {Object} props - Component props
 * @param {Array} props.styles - Available AI styles
 * @param {boolean} props.isLoading - Styles loading
 * @param {boolean} props.isGenerating - Transfer in progress
 * @param {number} props.progress - Progress percentage
 * @param {string} props.error - Error message
 * @param {Function} props.onApply - Apply style callback (receives styleIndex)
 */
const AiStylePanel = ({
    styles = [],
    isLoading,
    isGenerating,
    progress,
    error,
    onApply
}) => {
    const [selectedIndex, setSelectedIndex] = useState('0');

    if (isLoading) {
        return (
            <div className="pi-toolbar">
                <span className="pi-text-muted pi-text-sm">
                    {__('Loading styles...', 'personaliseit')}
                </span>
            </div>
        );
    }

    if (styles.length === 0) {
        return (
            <div className="pi-toolbar">
                <span className="pi-text-muted pi-text-sm">
                    {__('No styles available', 'personaliseit')}
                </span>
            </div>
        );
    }

    return (
        <div className="pi-toolbar">
            <div className="pi-flex-1">
                <select
                    className="pi-modern-select pi-select-transparent"
                    value={selectedIndex}
                    onChange={(e) => setSelectedIndex(e.target.value)}
                    aria-label={__('Select AI style', 'personaliseit')}
                >
                    {styles.map((style, idx) => (
                        <option key={style.id} value={idx}>
                            {style.label}
                        </option>
                    ))}
                </select>
            </div>

            <button
                type="button"
                className="pi-btn primary small"
                onClick={() => onApply(selectedIndex)}
                disabled={isGenerating}
            >
                {isGenerating ? (
                    __('Processing...', 'personaliseit')
                ) : (
                    <>
                        <span className="dashicons dashicons-superhero" aria-hidden="true"></span>
                        {__('AI Style', 'personaliseit')}
                    </>
                )}
            </button>

            {isGenerating && <ProgressBar progress={progress} />}

            {error && (
                <div className="pi-error-message">
                    {error}
                </div>
            )}
        </div>
    );
};

export default AiStylePanel;

/**
 * AiGeneratePanel - AI image generation UI
 * 
 * Prompt input and style selection for AI image generation.
 * 
 * @module AiGeneratePanel
 */
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import ProgressBar from '../../../common/components/ProgressBar';

/**
 * AI Generate panel component
 * @param {Object} props - Component props
 * @param {Array} props.styles - Available AI styles
 * @param {boolean} props.isGenerating - Generation in progress
 * @param {number} props.progress - Progress percentage
 * @param {Function} props.onGenerate - Generate callback
 * @param {Function} props.onCancel - Cancel callback
 */
const AiGeneratePanel = ({
    styles = [],
    isGenerating,
    progress,
    onGenerate,
    onCancel
}) => {
    const [prompt, setPrompt] = useState('');
    const [selectedStyleIndex, setSelectedStyleIndex] = useState('');
    const [removeBg, setRemoveBg] = useState(false);

    /**
     * Handle generate click
     */
    const handleGenerate = () => {
        let finalPrompt = prompt;
        if (selectedStyleIndex !== '' && styles[selectedStyleIndex]) {
            const suffix = styles[selectedStyleIndex].prompt;
            if (suffix) finalPrompt += `, ${suffix}`;
        }
        onGenerate(finalPrompt, removeBg);
    };

    return (
        <div className="pi-ai-panel">
            <textarea
                className="pi-modern-input pi-textarea"
                value={prompt}
                onChange={(e) => setPrompt(e.target.value)}
                placeholder={__('Describe image to generate...', 'personaliseit')}
                aria-label={__('Image description', 'personaliseit')}
            />

            <div className="pi-flex-row pi-gap-sm pi-wrap">
                {styles.length > 0 && (
                    <select
                        className="pi-modern-select pi-flex-1"
                        value={selectedStyleIndex}
                        onChange={(e) => setSelectedStyleIndex(e.target.value)}
                        aria-label={__('AI style', 'personaliseit')}
                    >
                        <option value="">{__('No Style (Default)', 'personaliseit')}</option>
                        {styles.map((s, i) => (
                            <option key={i} value={i}>{s.label}</option>
                        ))}
                    </select>
                )}

                <label className="pi-checkbox-label">
                    <input
                        type="checkbox"
                        checked={removeBg}
                        onChange={(e) => setRemoveBg(e.target.checked)}
                    />
                    {__('Remove BG', 'personaliseit')}
                </label>

                <button
                    type="button"
                    className="pi-btn primary small"
                    onClick={handleGenerate}
                    disabled={isGenerating || !prompt}
                >
                    {isGenerating ? __('Generating...', 'personaliseit') : __('Generate', 'personaliseit')}
                </button>

                <button
                    type="button"
                    className="pi-btn secondary small"
                    onClick={onCancel}
                >
                    {__('Cancel', 'personaliseit')}
                </button>
            </div>

            {isGenerating && <ProgressBar progress={progress} />}
        </div>
    );
};

export default AiGeneratePanel;

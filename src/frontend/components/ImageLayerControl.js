/**
 * ImageLayerControl - Orchestrator for image layer personalization
 * 
 * Manages the image upload, preview, and AI enhancement workflow.
 * Composed of focused sub-components for maintainability.
 * 
 * @module ImageLayerControl
 */
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import SpotifyTool from './SpotifyTool';
import MoonPhaseTool from './MoonPhaseTool';
import FaceCutoutTool from './FaceCutoutTool';
import { runBackgroundRemoval, fileFromBlob } from '../utils/backgroundRemoval';
import useImageUpload from '../hooks/useImageUpload';
import {
    ImageUploadDropzone,
    ImagePreviewControls,
    AiGeneratePanel,
    AiStylePanel,
    BackgroundRemovalButton,
    ToolButtonBar
} from './image';

/**
 * Image layer control component
 * @param {Object} props - Component props
 * @param {Object} props.layer - Layer configuration
 * @param {Object} props.userInputs - User input values
 * @param {Object} props.userStyles - User style values
 * @param {Function} props.updateInput - Update input callback
 * @param {Function} props.updateStyle - Update style callback
 * @param {Function} props.handleAddToCart - Sync cart callback
 * @param {string} props.personalisationMethod - Current mode
 */
const ImageLayerControl = ({
    layer,
    userInputs,
    userStyles,
    updateInput,
    updateStyle,
    handleAddToCart,
    personalisationMethod
}) => {
    // Active tool panel state
    const [activePanel, setActivePanel] = useState(null); // 'ai-generate' | 'spotify' | 'moon' | 'face-cutout' | null

    // AI styles state
    const [aiStyles, setAiStyles] = useState([]);
    const [isLoadingStyles, setIsLoadingStyles] = useState(false);
    const [aiError, setAiError] = useState('');

    // Settings from global config
    const enableAiGenerate = window.personaliseitData?.settings?.enableAiGenerate;
    const enableAiStyle = window.personaliseitData?.settings?.enableAiStyle;
    const aiStylePrompt = window.personaliseitData?.settings?.aiStylePrompt;
    const forceRemoveBg = window.personaliseitData?.settings?.forceRemoveBg;
    const enableSpotify = window.personaliseitData?.settings?.enableSpotify;

    // Filter valid styles
    const validStyles = aiStyles.filter(s => s.label && s.label.trim() !== '');
    const availableStyles = (layer.allowedAiStyles && layer.allowedAiStyles.length > 0)
        ? validStyles.filter(s => layer.allowedAiStyles.map(String).includes(String(s.id)))
        : validStyles;

    // Upload hook
    const upload = useImageUpload({
        layer,
        updateInput,
        handleAddToCart,
        personalisationMethod,
        availableStyles,
        aiStylePrompt
    });

    const hasImage = userInputs[layer.id] && userInputs[layer.id] !== '';

    // Fetch AI styles on mount
    useEffect(() => {
        if (enableAiGenerate || enableAiStyle) {
            setIsLoadingStyles(true);
            apiFetch({ path: '/wp/v2/personaliseit_style?_fields=id,title,meta&per_page=100' })
                .then(data => {
                    if (Array.isArray(data)) {
                        const formatted = data.map(item => ({
                            id: item.id,
                            label: item.title?.rendered || '',
                            prompt: item.meta?.personaliseit_style_prompt || '',
                            remove_bg: item.meta?.personaliseit_style_remove_bg || false
                        }));
                        setAiStyles(formatted);
                    }
                })
                .catch(() => {
                    // Silent fail - styles are optional
                })
                .finally(() => setIsLoadingStyles(false));
        }
    }, [enableAiGenerate, enableAiStyle]);

    /**
     * Handle AI generation
     */
    const handleAiGenerate = async (prompt, removeBg) => {
        upload.setIsGenerating(true);
        upload.startProgress();
        setAiError('');

        try {
            const data = await apiFetch({
                path: '/personaliseit/v1/ai/generate',
                method: 'POST',
                data: { prompt }
            });
            if (data.url) {
                updateInput(layer.id, data.url);
                handleAddToCart();
                setActivePanel(null);
            } else {
                setAiError(data.message || 'AI Generation failed');
            }
        } catch (e) {
            setAiError(__('AI Request failed.', 'personaliseit'));
        } finally {
            upload.finishProgress();
            upload.setIsGenerating(false);
        }
    };

    /**
     * Handle AI style transfer
     */
    const handleAiStyle = async (styleIndex) => {
        if (!confirm(__('This will replace the current image with a styled version. Continue?', 'personaliseit'))) return;

        upload.setIsGenerating(true);
        upload.startProgress();
        setAiError('');

        let styleSuffix = aiStylePrompt;
        let removeBg = forceRemoveBg;
        if (styleIndex !== '' && availableStyles[styleIndex]) {
            styleSuffix = availableStyles[styleIndex].prompt;
            if (availableStyles[styleIndex].remove_bg) removeBg = true;
        }

        try {
            const data = await apiFetch({
                path: '/personaliseit/v1/ai/style',
                method: 'POST',
                data: {
                    image: userInputs[layer.id],
                    prompt: styleSuffix
                }
            });

            if (data.url) {
                let finalUrl = data.url;

                if (removeBg) {
                    await new Promise(r => setTimeout(r, 500));
                    const blob = await runBackgroundRemoval(finalUrl);
                    if (blob) {
                        const formData = new FormData();
                        formData.append('file', fileFromBlob(blob, "bg_removed_" + Date.now() + ".png"));

                        const uploadHeader = {};
                        if (window.personaliseitData?.nonce) {
                            uploadHeader['X-WP-Nonce'] = window.personaliseitData.nonce;
                        }

                        try {
                            const uploadData = await apiFetch({
                                path: '/personaliseit/v1/upload',
                                method: 'POST',
                                body: formData
                            });
                            if (uploadData.url) {
                                finalUrl = uploadData.url;
                            }
                        } catch {
                            finalUrl = URL.createObjectURL(blob);
                        }
                    } else {
                        setAiError('Background removal failed.');
                    }
                }

                updateInput(layer.id, finalUrl);
                handleAddToCart();
            } else {
                setAiError(data.message || 'AI Style transfer failed');
            }
        } catch (e) {
            setAiError(__('AI Request failed.', 'personaliseit'));
        } finally {
            upload.finishProgress();
            upload.setIsGenerating(false);
        }
    };

    /**
     * Handle tool selection result
     */
    const handleToolSelect = (url) => {
        updateInput(layer.id, url);
        handleAddToCart();
        setActivePanel(null);
    };

    /**
     * Handle background removal complete
     */
    const handleBgRemovalComplete = (url) => {
        updateInput(layer.id, url);
        handleAddToCart();
    };

    // No image yet - show upload zone
    if (!hasImage) {
        return (
            <div className="control-group">
                <label className="pi-label-primary">
                    {layer.label || __('Upload Image', 'personaliseit')}
                    {layer.required && <span className="pi-required">*</span>}
                </label>

                <ImageUploadDropzone
                    isDragging={upload.isDragging}
                    isUploading={upload.isUploading}
                    inputRef={upload.inputRef}
                    onDragOver={upload.onDragOver}
                    onDragLeave={upload.onDragLeave}
                    onDrop={upload.onDrop}
                    onClick={upload.triggerFileInput}
                    onFileChange={upload.onFileInputChange}
                />

                {upload.uploadError && (
                    <div className="pi-error-message">{upload.uploadError}</div>
                )}

                {enableAiGenerate && personalisationMethod !== 'embroidery' && (
                    <div className="pi-tools-section">
                        {activePanel === null ? (
                            <ToolButtonBar
                                enableSpotify={enableSpotify}
                                onAiGenerate={() => setActivePanel('ai-generate')}
                                onSpotify={() => setActivePanel('spotify')}
                                onMoonPhase={() => setActivePanel('moon')}
                                onFaceCutout={() => setActivePanel('face-cutout')}
                            />
                        ) : activePanel === 'ai-generate' ? (
                            <AiGeneratePanel
                                styles={validStyles}
                                isGenerating={upload.isGenerating}
                                progress={upload.progress}
                                onGenerate={handleAiGenerate}
                                onCancel={() => setActivePanel(null)}
                            />
                        ) : activePanel === 'spotify' ? (
                            <SpotifyTool
                                onSelect={(data) => {
                                    const imageUrl = data.codeData || data.proxyUrl;
                                    handleToolSelect(imageUrl);
                                }}
                                onCancel={() => setActivePanel(null)}
                            />
                        ) : activePanel === 'moon' ? (
                            <MoonPhaseTool
                                onSelect={handleToolSelect}
                                onCancel={() => setActivePanel(null)}
                            />
                        ) : activePanel === 'face-cutout' ? (
                            <FaceCutoutTool
                                onSelect={handleToolSelect}
                                onCancel={() => setActivePanel(null)}
                            />
                        ) : null}
                    </div>
                )}
            </div>
        );
    }

    // Has image - show preview and controls
    return (
        <div className="control-group">
            <ImagePreviewControls
                layer={layer}
                userInputs={userInputs}
                userStyles={userStyles}
                updateInput={updateInput}
                updateStyle={updateStyle}
                handleAddToCart={handleAddToCart}
                personalisationMethod={personalisationMethod}
            />

            {layer.enableBackgroundRemoval && (
                <BackgroundRemovalButton
                    imageUrl={userInputs[layer.id]}
                    onComplete={handleBgRemovalComplete}
                />
            )}

            {personalisationMethod !== 'embroidery' && layer.allowedAiStyles && layer.allowedAiStyles.length > 0 && (
                <AiStylePanel
                    styles={availableStyles}
                    isLoading={isLoadingStyles}
                    isGenerating={upload.isGenerating}
                    progress={upload.progress}
                    error={aiError || upload.uploadError}
                    onApply={handleAiStyle}
                />
            )}
        </div>
    );
};

export default ImageLayerControl;

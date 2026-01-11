import { useState, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import SpotifyTool from './SpotifyTool';
import MoonPhaseTool from './MoonPhaseTool';
import FaceCutoutTool from './FaceCutoutTool';
import { runBackgroundRemoval, fileFromBlob } from '../utils/backgroundRemoval';

const ImageLayerControl = ({
    layer,
    userInputs,
    userStyles,
    updateInput,
    updateStyle,
    handleAddToCart,
    personalisationMethod
}) => {
    const [isDragging, setIsDragging] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const inputRef = useRef(null);
    const hasImage = userInputs[layer.id] && userInputs[layer.id] !== '';

    // AI State
    const [aiStyles, setAiStyles] = useState([]);
    const [isLoadingStyles, setIsLoadingStyles] = useState(false);
    const [isGenerating, setIsGenerating] = useState(false);
    const [prompt, setPrompt] = useState('');
    const [selectedStyleIndex, setSelectedStyleIndex] = useState('');
    const [transferStyleIndex, setTransferStyleIndex] = useState('');
    const [showAiInput, setShowAiInput] = useState(false);
    const [showSpotify, setShowSpotify] = useState(false);
    const [showMoonPhase, setShowMoonPhase] = useState(false);
    const [showFaceCutout, setShowFaceCutout] = useState(false);
    const [aiError, setAiError] = useState('');
    const [uploadError, setUploadError] = useState(null);
    const [progress, setProgress] = useState(0);
    const [logs, setLogs] = useState([]);
    const progressInterval = useRef(null);

    /**
     * Get image dimensions from a file.
     */
    const getImageDimensions = (file) => {
        return new Promise((resolve, reject) => {
            const img = new Image();
            const url = URL.createObjectURL(file);
            img.onload = () => {
                URL.revokeObjectURL(url);
                resolve({ width: img.width, height: img.height });
            };
            img.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error('Failed to load image'));
            };
            img.src = url;
        });
    };

    const addLog = (msg) => {
        // console.log(`[PersonaliseIt] ${msg}`);
    };

    // Filter valid styles - Moved up for accessibility
    const validStyles = aiStyles.filter(s => s.label && s.label.trim() !== '');
    const availableStyles = (layer.allowedAiStyles && layer.allowedAiStyles.length > 0)
        ? validStyles.filter(s => layer.allowedAiStyles.map(String).includes(String(s.id)))
        : validStyles;

    const handleFile = async (file) => {
        if (!file) return;

        // File validation
        const allowedFormats = layer.allowedFormats || ['jpg', 'png'];
        const maxFileSizeMB = layer.maxFileSizeMB || 10;
        const minWidth = layer.minWidth || 0;
        const minHeight = layer.minHeight || 0;

        // Check file format
        const fileExt = file.name.split('.').pop().toLowerCase();
        const mimeToExt = {
            'image/jpeg': 'jpg',
            'image/jpg': 'jpg',
            'image/png': 'png',
            'image/webp': 'webp',
            'image/heic': 'heic',
            'image/heif': 'heic'
        };
        const detectedExt = mimeToExt[file.type] || fileExt;

        if (!allowedFormats.includes(detectedExt) && !allowedFormats.includes(fileExt)) {
            setUploadError(__('Invalid file format. Allowed:', 'personaliseit') + ' ' + allowedFormats.map(f => f.toUpperCase()).join(', '));
            return;
        }

        // Check file size
        const fileSizeMB = file.size / (1024 * 1024);
        if (fileSizeMB > maxFileSizeMB) {
            setUploadError(__('File too large. Maximum:', 'personaliseit') + ' ' + maxFileSizeMB + 'MB');
            return;
        }

        // Check resolution (requires loading image first)
        if (minWidth > 0 || minHeight > 0) {
            try {
                const dimensions = await getImageDimensions(file);
                if (dimensions.width < minWidth || dimensions.height < minHeight) {
                    setUploadError(__('Image too small. Minimum:', 'personaliseit') + ` ${minWidth}x${minHeight}px`);
                    return;
                }
            } catch (e) {
                console.warn('Could not check image dimensions:', e);
            }
        }

        // Clear any previous errors
        setUploadError(null);

        if (file.type.startsWith('image/')) {
            setIsUploading(true);
            // Clear logs if starting a new flow
            setLogs([]);
            addLog('Upload Started: ' + file.name);

            const formData = new FormData();
            formData.append('file', file);

            // Flag for embroidery automatic flow
            const isEmbroidery = personalisationMethod === 'embroidery';
            if (isEmbroidery) addLog('Mode: Embroidery (Auto-Flow)');

            try {
                const restUrl = window.personaliseitData?.restUrl || '/wp-json/';
                const headers = {};
                if (window.personaliseitData?.nonce) {
                    headers['X-WP-Nonce'] = window.personaliseitData.nonce;
                }

                addLog('Uploading original image...');
                const res = await fetch(restUrl + 'personaliseit/v1/upload', {
                    method: 'POST',
                    headers: headers,
                    body: formData
                });

                if (!res.ok) throw new Error('Upload request failed');
                const text = await res.text();
                let data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    console.error('Upload Response Text:', text);
                    throw new Error('Server returned invalid JSON: ' + text.substring(0, 100) + '...');
                }

                if (data.url) {
                    // Standard success flow
                    let finalUrl = data.url;
                    addLog('Original Upload Success: ' + finalUrl);

                    // EMBROIDERY MODE: Automatic AI Style Transfer
                    if (isEmbroidery) {
                        setIsUploading(false); // Stop "Uploading"
                        setIsGenerating(true); // Start "Generating" (or Digitizing)
                        startProgress();

                        try {
                            // Determine style: Use first available or global fallback
                            let styleSuffix = aiStylePrompt;
                            let removeBg = false; // Default to false for auto-flow unless style specifies

                            if (availableStyles.length > 0) {
                                styleSuffix = availableStyles[0].prompt;
                                if (availableStyles[0].remove_bg) removeBg = true;
                            }

                            addLog(`Auto-Embroidery Style: Generating...`);

                            const styleRes = await fetch(restUrl + 'personaliseit/v1/ai/style', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-WP-Nonce': window.personaliseitData?.nonce
                                },
                                body: JSON.stringify({
                                    image: data.url, // Use the fresh upload URL
                                    prompt: styleSuffix
                                })
                            });

                            const styleData = await styleRes.json();
                            if (styleData.url) {
                                finalUrl = styleData.url;
                                addLog('AI Style Success: ' + finalUrl);

                                // Background Removal for Embroidery
                                if (removeBg) {
                                    addLog('Starting Auto-BG Removal...');
                                    await new Promise(r => setTimeout(r, 500));

                                    const blob = await runBackgroundRemoval(finalUrl);
                                    if (blob) {
                                        // addLog('Uploading BG-Removed Canvas...');
                                        const fd = new FormData();
                                        fd.append('file', fileFromBlob(blob, "embroidery_clean_" + Date.now() + ".png"));

                                        const upRes = await fetch(restUrl + 'personaliseit/v1/upload', {
                                            method: 'POST',
                                            headers: headers,
                                            body: fd
                                        });
                                        const upData = await upRes.json();
                                        if (upData.url) {
                                            finalUrl = upData.url;
                                            // addLog('Auto-BG Upload Complete: ' + finalUrl);
                                        } else {
                                            addLog('Auto-BG Upload Failed: ' + (upData.message || 'Unknown'));
                                        }
                                    } else {
                                        addLog('Auto-BG Removal Failed (Blob null).');
                                    }
                                }

                            } else {
                                console.error('Auto-Embroidery Failed:', styleData.message);
                                setAiError(__('Auto-digitizing failed, using original image.', 'personaliseit'));
                                addLog('AI Style API Failed: ' + styleData.message);
                            }
                        } catch (err) {
                            console.error('Auto-Embroidery Error:', err);
                            setAiError(__('Auto-digitizing error, using original image.', 'personaliseit'));
                            addLog('Auto-Embroidery Exception: ' + err.message);
                        } finally {
                            finishProgress();
                            setIsGenerating(false);
                        }
                    }

                    updateInput(layer.id, finalUrl);
                    handleAddToCart();
                } else {
                    throw new Error(data.message || 'Unknown upload error');
                }
            } catch (e) {
                console.error('Upload error detail:', e);
                addLog('Fatal Upload Error: ' + e.message);
                alert(__('Image upload failed: ', 'personaliseit') + e.message);
            } finally {
                if (!isEmbroidery) {
                    setIsUploading(false);
                }
            }
        }
    };

    const onDragOver = (e) => {
        e.preventDefault();
        setIsDragging(true);
    };

    const onDragLeave = () => setIsDragging(false);

    const onDrop = (e) => {
        e.preventDefault();
        setIsDragging(false);
        handleFile(e.dataTransfer.files[0]);
    };

    const enableAiGenerate = window.personaliseitData?.settings?.enableAiGenerate;
    const enableAiStyle = window.personaliseitData?.settings?.enableAiStyle;
    const aiStylePrompt = window.personaliseitData?.settings?.aiStylePrompt;
    const forceRemoveBg = window.personaliseitData?.settings?.forceRemoveBg;
    const enableSpotify = window.personaliseitData?.settings?.enableSpotify;


    const startProgress = () => {
        setProgress(0);
        if (progressInterval.current) clearInterval(progressInterval.current);
        progressInterval.current = setInterval(() => {
            setProgress(prev => {
                if (prev >= 90) return prev;
                return prev + Math.random() * 8;
            });
        }, 300);
    };

    const finishProgress = () => {
        if (progressInterval.current) clearInterval(progressInterval.current);
        setProgress(100);
        setTimeout(() => setProgress(0), 1000);
    };

    useEffect(() => {
        return () => {
            if (progressInterval.current) clearInterval(progressInterval.current);
        };
    }, []);

    // Fetch Styles
    useEffect(() => {
        if (enableAiGenerate || enableAiStyle) {
            setIsLoadingStyles(true);
            fetch((window.personaliseitData?.restUrl || '/wp-json/') + 'wp/v2/personaliseit_style?_fields=id,title,meta&per_page=100')
                .then(res => res.json())
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
                .catch(err => console.error('Failed to load styles', err))
                .finally(() => setIsLoadingStyles(false));
        }
    }, [enableAiGenerate, enableAiStyle]);

    const handleAiGenerate = async () => {
        if (!prompt) return;
        setIsGenerating(true);
        startProgress();
        setAiError('');

        let finalPrompt = prompt;
        if (selectedStyleIndex !== '' && availableStyles[selectedStyleIndex]) {
            const suffix = availableStyles[selectedStyleIndex].prompt;
            if (suffix) finalPrompt += `, ${suffix}`;
        }

        try {
            const res = await fetch((window.personaliseitData?.restUrl || '/wp-json/') + 'personaliseit/v1/ai/generate', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.personaliseitData?.nonce
                },
                body: JSON.stringify({ prompt: finalPrompt })
            });
            const data = await res.json();
            if (data.url) {
                updateInput(layer.id, data.url);
                handleAddToCart();
                setShowAiInput(false);
                setPrompt('');
            } else {
                alert(data.message || 'AI Generation failed');
            }
        } catch (e) {
            console.error(e);
            alert(__('AI Request failed.', 'personaliseit'));
        } finally {
            finishProgress();
            setIsGenerating(false);
        }
    };

    const handleAiStyle = async () => {
        if (!confirm(__('This will replace the current image with a styled version. Continue?', 'personaliseit'))) return;

        setIsGenerating(true);
        setLogs([]); // Clear previous logs
        addLog('Starting AI Style Process...');

        startProgress();
        setAiError('');

        let styleSuffix = aiStylePrompt;
        let removeBg = forceRemoveBg;
        if (transferStyleIndex !== '' && availableStyles[transferStyleIndex]) {
            styleSuffix = availableStyles[transferStyleIndex].prompt;
            if (availableStyles[transferStyleIndex].remove_bg) removeBg = true;
        }

        addLog(`Processing AI Style Request...`);

        try {
            addLog('Fetching /personaliseit/v1/ai/style...');
            const res = await fetch((window.personaliseitData?.restUrl || '/wp-json/') + 'personaliseit/v1/ai/style', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': window.personaliseitData?.nonce
                },
                body: JSON.stringify({
                    image: userInputs[layer.id],
                    prompt: styleSuffix
                })
            });
            const data = await res.json();

            if (data.url) {
                let finalUrl = data.url;
                if (finalUrl.startsWith('data:')) {
                    addLog('AI Generation Complete: [Base64 Data Truncated]');
                } else {
                    addLog(`AI Generation Complete: ${finalUrl}`);
                }

                if (removeBg) {
                    addLog('Waiting 500ms for file sync...');
                    await new Promise(r => setTimeout(r, 500));

                    const blob = await runBackgroundRemoval(finalUrl);
                    if (blob) {
                        addLog('Uploading processed transparent image...');
                        const formData = new FormData();
                        formData.append('file', fileFromBlob(blob, "bg_removed_" + Date.now() + ".png"));

                        const uploadHeader = {};
                        if (window.personaliseitData?.nonce) {
                            uploadHeader['X-WP-Nonce'] = window.personaliseitData.nonce;
                        }

                        try {
                            const uploadRes = await fetch((window.personaliseitData?.restUrl || '/wp-json/') + 'personaliseit/v1/upload', {
                                method: 'POST',
                                headers: uploadHeader,
                                body: formData
                            });
                            const uploadData = await uploadRes.json();
                            if (uploadData.url) {
                                finalUrl = uploadData.url;
                                addLog(`Upload Success: ${finalUrl}`);
                            }
                        } catch (e) {
                            addLog(`Upload Failed: ${e.message}`);
                            // Fallback to local url
                            finalUrl = URL.createObjectURL(blob);
                            addLog('Using Blob URL Fallback.');
                        }
                    } else {
                        addLog('CRITICAL: All BG Removal methods returned null.');
                        setAiError('Background removal failed. Check Debug Log.');
                    }
                }

                updateInput(layer.id, finalUrl);
                handleAddToCart();
                addLog('Process Finished Successfully.');
            } else {
                addLog('AI Style Failed: ' + (data.message || 'Unknown'));
                setAiError(data.message || 'AI Style transfer failed');
            }
        } catch (e) {
            addLog('Fatal Exception: ' + e.message);
            setAiError(__('AI Request failed.', 'personaliseit'));
        } finally {
            finishProgress();
            setIsGenerating(false);
        }
    };



    // If no image is selected yet, show DropZone
    if (!hasImage) {
        return (
            <div className="control-group">
                <label style={{ fontWeight: '600', marginBottom: '8px', display: 'block' }}>
                    {layer.label || __('Upload Image', 'personaliseit')}
                    {layer.required && <span style={{ color: '#d63638', marginLeft: '3px' }}>*</span>}
                </label>



                {/* Hidden File Input (Outside Dropzone to prevent bubbling loop) */}
                <input
                    ref={inputRef}
                    type="file"
                    accept="image/*"
                    style={{ display: 'none' }}
                    onChange={(e) => handleFile(e.target.files[0])}
                    disabled={isUploading}
                />

                <div
                    className={`personaliseit-dropzone ${isDragging ? 'dragging' : ''}`}
                    onDragOver={onDragOver}
                    onDragLeave={onDragLeave}
                    onDrop={onDrop}
                    onClick={() => inputRef.current?.click()}
                >
                    {isUploading ? (
                        <p>{__('Uploading...', 'personaliseit')}</p>
                    ) : (
                        <>
                            <span className="dashicons dashicons-upload"></span>
                            <p>{__('Click or Drag to Upload Image', 'personaliseit')}</p>
                        </>
                    )}
                </div>

                {enableAiGenerate && personalisationMethod !== 'embroidery' && (
                    <div className="ai-generate-section" style={{ marginTop: '10px' }}>
                        {!showAiInput && !showSpotify && !showMoonPhase && !showFaceCutout ? (
                            <div style={{ display: 'flex', gap: '5px' }}>
                                <button
                                    type="button"
                                    className="pi-btn secondary small"
                                    onClick={() => setShowAiInput(true)}
                                    style={{ flex: 1 }}
                                >
                                    <span className="dashicons dashicons-art" style={{ marginRight: '4px' }}></span>
                                    {__('AI Gen', 'personaliseit')}
                                </button>
                                {enableSpotify && (
                                    <button
                                        type="button"
                                        className="pi-btn secondary small"
                                        onClick={() => setShowSpotify(true)}
                                        style={{ flex: 1 }}
                                    >
                                        <span className="dashicons dashicons-format-audio" style={{ marginRight: '4px' }}></span>
                                        {__('Spotify', 'personaliseit')}
                                    </button>
                                )}
                                <button
                                    type="button"
                                    className="pi-btn secondary small"
                                    onClick={() => setShowMoonPhase(true)}
                                    style={{ flex: 1 }}
                                >
                                    <span className="dashicons dashicons-update" style={{ marginRight: '4px' }}></span>
                                    {__('Moon', 'personaliseit')}
                                </button>
                                <button
                                    type="button"
                                    className="pi-btn secondary small"
                                    onClick={() => setShowFaceCutout(true)}
                                    style={{ flex: 1 }}
                                >
                                    <span className="dashicons dashicons-admin-users" style={{ marginRight: '4px' }}></span>
                                    {__('Face Cut', 'personaliseit')}
                                </button>
                            </div>
                        ) : showAiInput ? (
                            <div className="ai-input-group" style={{ background: '#f8f9fa', padding: '10px', borderRadius: '4px', border: '1px solid #eee' }}>
                                <textarea
                                    className="pi-modern-input"
                                    value={prompt}
                                    onChange={(e) => setPrompt(e.target.value)}
                                    placeholder={__('Describe image to generate...', 'personaliseit')}
                                    style={{ width: '100%', marginBottom: '8px', minHeight: '60px' }}
                                />
                                <div style={{ display: 'flex', gap: '8px', flexWrap: 'wrap' }}>
                                    {validStyles.length > 0 && (
                                        <select
                                            className="pi-modern-select"
                                            value={selectedStyleIndex}
                                            onChange={(e) => setSelectedStyleIndex(e.target.value)}
                                            style={{ flex: 1, minWidth: '120px' }}
                                        >
                                            <option value="">{__('No Style (Default)', 'personaliseit')}</option>
                                            {validStyles.map((s, i) => (
                                                <option key={i} value={i}>{s.label}</option>
                                            ))}
                                        </select>
                                    )}
                                    {/* Force BG Removal Toggle */}
                                    <label style={{ display: 'flex', alignItems: 'center', fontSize: '12px', gap: '4px', cursor: 'pointer', background: '#fff', padding: '0 8px', borderRadius: '4px', border: '1px solid #ddd' }}>
                                        <input
                                            type="checkbox"
                                            checked={forceRemoveBg}
                                            onChange={(e) => setForceRemoveBg(e.target.checked)}
                                        />
                                        {__('Remove BG', 'personaliseit')}
                                    </label>
                                    <button
                                        type="button"
                                        className="pi-btn primary small"
                                        onClick={handleAiGenerate}
                                        disabled={isGenerating || !prompt}
                                    >
                                        {isGenerating ? __('Generating...', 'personaliseit') : __('Generate', 'personaliseit')}
                                    </button>
                                    <button
                                        type="button"
                                        className="pi-btn secondary small"
                                        onClick={() => setShowAiInput(false)}
                                    >
                                        {__('Cancel', 'personaliseit')}
                                    </button>
                                </div>
                                {/* Progress Bar for Generate */}
                                {isGenerating && (
                                    <div style={{ marginTop: '10px', width: '100%', background: '#e0e0e0', borderRadius: '4px', height: '6px', overflow: 'hidden' }}>
                                        <div style={{ width: `${progress}%`, background: '#2271b1', height: '100%', transition: 'width 0.3s ease' }}></div>
                                    </div>
                                )}
                            </div>
                        ) : showSpotify ? (
                            <SpotifyTool
                                onSelect={(data) => {
                                    // Handle both paste mode (codeData) and search mode (proxyUrl)
                                    const imageUrl = data.codeData || data.proxyUrl;
                                    updateInput(layer.id, imageUrl);
                                    handleAddToCart();
                                    setShowSpotify(false);
                                }}
                                onCancel={() => setShowSpotify(false)}
                            />
                        ) : showMoonPhase ? (
                            <MoonPhaseTool
                                onSelect={(url) => {
                                    updateInput(layer.id, url);
                                    handleAddToCart();
                                    setShowMoonPhase(false);
                                }}
                                onCancel={() => setShowMoonPhase(false)}
                            />
                        ) : showFaceCutout ? (
                            <FaceCutoutTool
                                onSelect={(url) => {
                                    updateInput(layer.id, url);
                                    handleAddToCart();
                                    setShowFaceCutout(false);
                                }}
                                onCancel={() => setShowFaceCutout(false)}
                            />
                        ) : null}
                    </div>
                )}
            </div>
        );
    }



    // Modern Condensed Layout for Active Image
    return (
        <div className="control-group">
            <div className="pi-flex-row" style={{ alignItems: 'flex-start' }}>

                {/* Left: Compact Preview */}
                <div className="pi-compact-preview">
                    {userInputs[layer.id] && (
                        <img
                            src={userInputs[layer.id]}
                            alt="Preview"
                        />
                    )}
                </div>

                {/* Right: Controls Stack */}
                <div className="pi-flex-col" style={{ flex: 1 }}>
                    <div className="pi-flex-row pi-between">
                        <label style={{ fontWeight: '600', fontSize: '0.95em' }}>{layer.label || 'Image'}</label>
                        <button
                            className="pi-btn danger small"
                            onClick={() => {
                                updateInput(layer.id, '');
                                updateStyle(layer.id, { filter: 'none' });
                                handleAddToCart();
                            }}
                            title={__('Remove Image', 'personaliseit')}
                        >
                            <span className="dashicons dashicons-trash" style={{ margin: 0 }}></span>
                        </button>
                    </div>

                    {/* Filter & Crop - HIDDEN IN EMBROIDERY MODE */}
                    {personalisationMethod !== 'embroidery' && (
                        <div className="pi-flex-row">
                            <select
                                className="pi-modern-select"
                                style={{ flex: 1 }}
                                onChange={(e) => {
                                    updateStyle(layer.id, { filter: e.target.value });
                                    handleAddToCart();
                                }}
                                value={userStyles[layer.id]?.filter || 'none'}
                                title={__('Apply Filter', 'personaliseit')}
                            >
                                <option value="none">{__('No Filter', 'personaliseit')}</option>
                                <option value="grayscale">{__('Greyscale', 'personaliseit')}</option>
                                <option value="sepia">{__('Sepia', 'personaliseit')}</option>
                                <option value="brightness">{__('Brighten', 'personaliseit')}</option>
                                <option value="contrast">{__('High Contrast', 'personaliseit')}</option>
                            </select>

                            <select
                                className="pi-modern-select"
                                style={{ flex: 1 }}
                                onChange={(e) => {
                                    updateStyle(layer.id, { maskShape: e.target.value });
                                    handleAddToCart();
                                }}
                                value={userStyles[layer.id]?.maskShape || 'none'}
                                title={__('Crop Shape', 'personaliseit')}
                            >
                                <option value="none">{__('No Crop', 'personaliseit')}</option>
                                <option value="circle">{__('Circle', 'personaliseit')}</option>
                                <option value="heart">{__('Heart', 'personaliseit')}</option>
                                <option value="star">{__('Star', 'personaliseit')}</option>
                            </select>
                        </div>
                    )}

                    {/* Remove Background Button (when enabled for layer) */}
                    {layer.enableBackgroundRemoval && (
                        <div style={{ marginTop: '8px' }}>
                            <button
                                type="button"
                                className="pi-btn secondary small"
                                onClick={async () => {
                                    const imageUrl = userInputs[layer.id];
                                    if (!imageUrl) return;

                                    setIsGenerating(true);
                                    setProgress(0);
                                    startProgress();

                                    try {
                                        const blob = await runBackgroundRemoval(imageUrl, addLog, (key, pct) => {
                                            if (pct > progress) setProgress(Math.min(pct, 95));
                                        });

                                        if (blob) {
                                            // Upload the processed image
                                            const restUrl = window.personaliseitData?.restUrl || '/wp-json/';
                                            const file = fileFromBlob(blob, 'nobg-' + Date.now() + '.png');
                                            const formData = new FormData();
                                            formData.append('file', file);

                                            const headers = {};
                                            if (window.personaliseitData?.nonce) {
                                                headers['X-WP-Nonce'] = window.personaliseitData.nonce;
                                            }

                                            const res = await fetch(restUrl + 'personaliseit/v1/upload', {
                                                method: 'POST',
                                                headers: headers,
                                                body: formData
                                            });

                                            const data = await res.json();
                                            if (data.url) {
                                                updateInput(layer.id, data.url);
                                                handleAddToCart();
                                            }
                                        }
                                    } catch (e) {
                                        console.error('Background removal failed:', e);
                                    } finally {
                                        finishProgress();
                                        setIsGenerating(false);
                                        setProgress(100);
                                    }
                                }}
                                disabled={isGenerating}
                                style={{ width: '100%', display: 'flex', alignItems: 'center', justifyContent: 'center', gap: '5px' }}
                            >
                                <span className="dashicons dashicons-image-crop" style={{ margin: 0, fontSize: '14px' }}></span>
                                {isGenerating ? __('Removing...', 'personaliseit') : __('Remove Background', 'personaliseit')}
                            </button>
                            {isGenerating && (
                                <div style={{ marginTop: '5px', width: '100%', background: '#e0e0e0', borderRadius: '4px', height: '4px', overflow: 'hidden' }}>
                                    <div style={{ width: `${progress}%`, background: '#7c3aed', height: '100%', transition: 'width 0.3s ease' }}></div>
                                </div>
                            )}
                        </div>
                    )}

                    {/* AI Style Transfer Section (Condensed) - HIDDEN IN EMBROIDERY MODE */}
                    {personalisationMethod !== 'embroidery' && layer.allowedAiStyles && layer.allowedAiStyles.length > 0 && (
                        <div className="pi-toolbar" style={{ marginTop: 0, padding: '5px 8px' }}>
                            {isLoadingStyles ? (
                                <span style={{ fontSize: '12px', color: '#666' }}>{__('Loading styles...', 'personaliseit')}</span>
                            ) : availableStyles.length > 0 ? (
                                <>
                                    <div style={{ flex: 1 }}>
                                        <select
                                            className="pi-modern-select"
                                            value={transferStyleIndex}
                                            onChange={(e) => setTransferStyleIndex(e.target.value)}
                                            style={{ width: '100%', border: 'none', background: 'transparent' }}
                                        >
                                            {availableStyles.map((style, idx) => (
                                                <option key={style.id} value={idx}>
                                                    {style.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                    <button
                                        className="pi-btn primary small"
                                        onClick={handleAiStyle}
                                        disabled={isGenerating}
                                        style={{ minWidth: '90px' }}
                                    >
                                        {isGenerating ? (
                                            __('Processing...', 'personaliseit')
                                        ) : (
                                            <>
                                                <span className="dashicons dashicons-superhero"></span>
                                                {__('AI Style', 'personaliseit')}
                                            </>
                                        )}
                                    </button>
                                </>
                            ) : (
                                <span style={{ fontSize: '12px', color: '#999' }}>{__('No styles available', 'personaliseit')}</span>
                            )}

                            {/* Progress Bar for Transfer */}
                            {isGenerating && (
                                <div style={{ width: '100%', background: '#e0e0e0', borderRadius: '4px', height: '4px', overflow: 'hidden', marginTop: '5px' }}>
                                    <div style={{ width: `${progress}%`, background: '#2271b1', height: '100%', transition: 'width 0.3s ease' }}></div>
                                </div>
                            )}

                            {aiError && <div style={{ width: '100%', color: '#d63638', fontSize: '11px', marginTop: '4px' }}>{aiError}</div>}
                            {uploadError && <div style={{ width: '100%', color: '#d63638', fontSize: '12px', marginTop: '8px', padding: '8px', background: '#fff2f2', borderRadius: '4px', border: '1px solid #ffb3b3' }}>{uploadError}</div>}
                        </div>
                    )}
                </div>
            </div>

        </div>
    );
};
export default ImageLayerControl;

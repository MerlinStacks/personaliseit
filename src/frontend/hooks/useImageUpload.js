/**
 * useImageUpload - Custom hook for image upload logic
 * 
 * Handles file validation, upload to server, and embroidery auto-flow.
 * Extracted from ImageLayerControl for testability and reuse.
 * 
 * @module useImageUpload
 */
import { useState, useRef, useCallback, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { runBackgroundRemoval, fileFromBlob } from '../utils/backgroundRemoval';

/**
 * Get image dimensions from a file
 * @param {File} file - Image file
 * @returns {Promise<{width: number, height: number}>}
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

/**
 * Custom hook for image upload functionality
 * @param {Object} options - Hook options
 * @param {Object} options.layer - Layer configuration
 * @param {Function} options.updateInput - Callback to update layer input
 * @param {Function} options.handleAddToCart - Callback to sync cart
 * @param {string} options.personalisationMethod - Current personalization mode
 * @param {Array} options.availableStyles - Available AI styles
 * @param {string} options.aiStylePrompt - Default AI style prompt
 * @returns {Object} Upload state and handlers
 */
export const useImageUpload = ({
    layer,
    updateInput,
    handleAddToCart,
    personalisationMethod,
    availableStyles = [],
    aiStylePrompt = ''
}) => {
    const [isDragging, setIsDragging] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const [isGenerating, setIsGenerating] = useState(false);
    const [uploadError, setUploadError] = useState(null);
    const [progress, setProgress] = useState(0);
    const inputRef = useRef(null);
    const progressInterval = useRef(null);

    /**
     * Start fake progress animation for long operations
     */
    const startProgress = useCallback(() => {
        setProgress(0);
        if (progressInterval.current) clearInterval(progressInterval.current);
        progressInterval.current = setInterval(() => {
            setProgress(prev => {
                if (prev >= 90) return prev;
                return prev + Math.random() * 8;
            });
        }, 300);
    }, []);

    /**
     * Complete progress and cleanup
     */
    const finishProgress = useCallback(() => {
        if (progressInterval.current) clearInterval(progressInterval.current);
        setProgress(100);
        setTimeout(() => setProgress(0), 1000);
    }, []);

    // Cleanup interval on unmount
    useEffect(() => {
        return () => {
            if (progressInterval.current) clearInterval(progressInterval.current);
        };
    }, []);

    /**
     * Validate and process uploaded file
     * @param {File} file - File to process
     */
    const handleFile = useCallback(async (file) => {
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

        // Check resolution
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

        // Clear previous errors
        setUploadError(null);

        if (file.type.startsWith('image/')) {
            setIsUploading(true);
            const isEmbroidery = personalisationMethod === 'embroidery';

            try {
                const formData = new FormData();
                formData.append('file', file);

                const data = await apiFetch({
                    path: '/personaliseit/v1/upload',
                    method: 'POST',
                    body: formData
                });

                if (data.url) {
                    let finalUrl = data.url;

                    // Embroidery mode: Automatic AI Style Transfer
                    if (isEmbroidery) {
                        setIsUploading(false);
                        setIsGenerating(true);
                        startProgress();

                        try {
                            let styleSuffix = aiStylePrompt;
                            let removeBg = false;

                            if (availableStyles.length > 0) {
                                styleSuffix = availableStyles[0].prompt;
                                if (availableStyles[0].remove_bg) removeBg = true;
                            }

                            const styleData = await apiFetch({
                                path: '/personaliseit/v1/ai/style',
                                method: 'POST',
                                data: {
                                    image: data.url,
                                    prompt: styleSuffix
                                }
                            });

                            if (styleData.url) {
                                finalUrl = styleData.url;

                                // Background removal for embroidery
                                if (removeBg) {
                                    await new Promise(r => setTimeout(r, 500));
                                    const blob = await runBackgroundRemoval(finalUrl);
                                    if (blob) {
                                        const fd = new FormData();
                                        fd.append('file', fileFromBlob(blob, "embroidery_clean_" + Date.now() + ".png"));

                                        const upData = await apiFetch({
                                            path: '/personaliseit/v1/upload',
                                            method: 'POST',
                                            body: fd
                                        });
                                        if (upData.url) {
                                            finalUrl = upData.url;
                                        }
                                    }
                                }
                            } else {
                                setUploadError(__('Auto-digitizing failed, using original image.', 'personaliseit'));
                            }
                        } catch {
                            setUploadError(__('Auto-digitizing error, using original image.', 'personaliseit'));
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
                setUploadError(__('Image upload failed: ', 'personaliseit') + (e.message || 'Unknown error'));
            } finally {
                if (!isEmbroidery) {
                    setIsUploading(false);
                }
            }
        }
    }, [layer, updateInput, handleAddToCart, personalisationMethod, availableStyles, aiStylePrompt, startProgress, finishProgress]);

    /**
     * Drag over handler
     */
    const onDragOver = useCallback((e) => {
        e.preventDefault();
        setIsDragging(true);
    }, []);

    /**
     * Drag leave handler
     */
    const onDragLeave = useCallback(() => {
        setIsDragging(false);
    }, []);

    /**
     * Drop handler
     */
    const onDrop = useCallback((e) => {
        e.preventDefault();
        setIsDragging(false);
        handleFile(e.dataTransfer.files[0]);
    }, [handleFile]);

    /**
     * Trigger file input click
     */
    const triggerFileInput = useCallback(() => {
        inputRef.current?.click();
    }, []);

    /**
     * Handle file input change
     */
    const onFileInputChange = useCallback((e) => {
        handleFile(e.target.files[0]);
    }, [handleFile]);

    return {
        // State
        isDragging,
        isUploading,
        isGenerating,
        uploadError,
        progress,
        inputRef,
        // Handlers
        handleFile,
        onDragOver,
        onDragLeave,
        onDrop,
        triggerFileInput,
        onFileInputChange,
        startProgress,
        finishProgress,
        setIsGenerating,
        setProgress,
        setUploadError
    };
};

export default useImageUpload;

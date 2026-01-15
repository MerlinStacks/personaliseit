/**
 * BackgroundRemovalButton - Button to remove image background
 * 
 * Self-contained component for background removal with progress.
 * 
 * @module BackgroundRemovalButton
 */
import { useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { runBackgroundRemoval, fileFromBlob } from '../../utils/backgroundRemoval';
import ProgressBar from '../../../common/components/ProgressBar';

/**
 * Background removal button with progress
 * @param {Object} props - Component props
 * @param {string} props.imageUrl - Current image URL
 * @param {Function} props.onComplete - Callback when removal completes (receives new URL)
 */
const BackgroundRemovalButton = ({ imageUrl, onComplete }) => {
    const [isProcessing, setIsProcessing] = useState(false);
    const [progress, setProgress] = useState(0);
    const progressInterval = useRef(null);

    /**
     * Start progress animation
     */
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

    /**
     * Finish progress animation
     */
    const finishProgress = () => {
        if (progressInterval.current) clearInterval(progressInterval.current);
        setProgress(100);
        setTimeout(() => setProgress(0), 1000);
    };

    /**
     * Handle background removal
     */
    const handleRemoveBackground = async () => {
        if (!imageUrl) return;

        setIsProcessing(true);
        startProgress();

        try {
            const blob = await runBackgroundRemoval(imageUrl, () => { }, (key, pct) => {
                if (pct > progress) setProgress(Math.min(pct, 95));
            });

            if (blob) {
                const file = fileFromBlob(blob, 'nobg-' + Date.now() + '.png');
                const formData = new FormData();
                formData.append('file', file);

                const data = await apiFetch({
                    path: '/personaliseit/v1/upload',
                    method: 'POST',
                    body: formData
                });

                if (data.url) {
                    onComplete(data.url);
                }
            }
        } catch {
            // Silent fail - background removal is optional
        } finally {
            finishProgress();
            setIsProcessing(false);
        }
    };

    return (
        <div className="pi-bg-removal">
            <button
                type="button"
                className="pi-btn secondary small pi-btn-full"
                onClick={handleRemoveBackground}
                disabled={isProcessing}
                aria-label={__('Remove background from image', 'personaliseit')}
            >
                <span className="dashicons dashicons-image-crop" aria-hidden="true"></span>
                {isProcessing ? __('Removing...', 'personaliseit') : __('Remove Background', 'personaliseit')}
            </button>

            {isProcessing && <ProgressBar progress={progress} color="#7c3aed" height="4px" />}
        </div>
    );
};

export default BackgroundRemovalButton;

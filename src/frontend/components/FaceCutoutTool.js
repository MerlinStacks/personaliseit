import { useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { runBackgroundRemoval, fileFromBlob } from '../utils/backgroundRemoval';

const FaceCutoutTool = ({ onSelect, onCancel }) => {
    const [isProcessing, setIsProcessing] = useState(false);
    const [progress, setProgress] = useState(0);
    const [error, setError] = useState('');
    const inputRef = useRef(null);

    // Mock progress removed in favor of real callback from backgroundRemoval utility

    const handleFile = async (file) => {
        if (!file) return;

        setIsProcessing(true);
        setError('');
        setProgress(0);

        // Progress Handler for real status
        const onProgress = (key, percent) => {
            let base = 0;
            let scale = 1;
            // 'fetch' phases (downloading models) -> 0-40% 
            // 'compute' phases (inference) -> 40-100%
            if (key.includes('fetch')) {
                scale = 0.4;
            } else {
                base = 40;
                scale = 0.6;
            }
            if (percent !== undefined) {
                setProgress(Math.min(95, base + (percent * scale)));
            }
        };

        try {
            // 1. Create a local URL for the file to pass to imgly
            const localUrl = URL.createObjectURL(file);

            // 2. Run BG Removal with Progress
            const blob = await runBackgroundRemoval(
                localUrl,
                () => { }, // No-op: debug logging disabled in production
                onProgress // Pass callback
            );

            if (!blob) {
                throw new Error('Background removal failed.');
            }

            setProgress(95); // Almost done

            // 3. Upload the result
            const formData = new FormData();
            formData.append('file', fileFromBlob(blob, `face_cutout_${Date.now()}.png`));

            const data = await apiFetch({
                path: '/personaliseit/v1/upload',
                method: 'POST',
                body: formData
            });

            if (data.url) {
                setProgress(100);
                setTimeout(() => {
                    onSelect(data.url);
                }, 200);
            } else {
                throw new Error(data.message || 'Upload failed');
            }

        } catch (e) {
            setError(e.message || __('Face cutout failed', 'personaliseit'));
            setIsProcessing(false);
        }
    };

    return (
        <div className="pi-tool-card face-cutout-tool-container">
            {isProcessing ? (
                <div className="pi-processing-state">
                    <div className="pi-spinner" />
                    <p className="pi-processing-text">{__('Processing Cutout...', 'personaliseit')}</p>
                    <div className="pi-progress-bar">
                        <div className="pi-progress-bar__fill" style={{ width: `${progress}%` }} />
                    </div>
                    <span className="pi-progress-percent">{progress}%</span>
                </div>
            ) : (
                <>
                    <div className="pi-tool-card__header">
                        <span className="dashicons dashicons-format-image" />
                        {__('Upload Photo for Face Cutout', 'personaliseit')}
                    </div>

                    <input
                        ref={inputRef}
                        type="file"
                        accept="image/*"
                        style={{ display: 'none' }}
                        onChange={(e) => handleFile(e.target.files[0])}
                    />

                    <button
                        className="pi-btn primary"
                        onClick={() => inputRef.current?.click()}
                        style={{ width: '100%', marginBottom: 'var(--pi-space-3)' }}
                    >
                        <span className="dashicons dashicons-camera" />
                        {__('Select Photo', 'personaliseit')}
                    </button>

                    {error && (
                        <div className="pi-error-message">
                            <span className="dashicons dashicons-warning" />
                            {error}
                        </div>
                    )}

                    <button
                        className="pi-btn secondary"
                        onClick={onCancel}
                        style={{ width: '100%' }}
                    >
                        {__('Cancel', 'personaliseit')}
                    </button>
                </>
            )}
        </div>
    );
};

export default FaceCutoutTool;

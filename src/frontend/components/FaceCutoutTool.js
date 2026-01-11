import { useState, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
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

            const restUrl = window.personaliseitData?.restUrl || '/wp-json/';
            const headers = {};
            if (window.personaliseitData?.nonce) {
                headers['X-WP-Nonce'] = window.personaliseitData.nonce;
            }

            const res = await fetch(restUrl + 'personaliseit/v1/upload', {
                method: 'POST',
                headers: headers,
                body: formData
            });

            if (!res.ok) throw new Error('Upload failed');
            const data = await res.json();

            if (data.url) {
                setProgress(100);
                setTimeout(() => {
                    onSelect(data.url);
                }, 200);
            } else {
                throw new Error(data.message || 'Upload failed');
            }

        } catch (e) {
            console.error(e);
            setError(e.message);
            setIsProcessing(false);
        }
    };

    return (
        <div className="face-cutout-tool-container" style={{ background: '#f8f9fa', padding: '15px', borderRadius: '4px', border: '1px solid #eee', marginTop: '10px', textAlign: 'center' }}>

            {isProcessing ? (
                <div className="processing-state">
                    <div className="spinner" style={{ margin: '0 auto 10px', width: '30px', height: '30px', border: '3px solid #f3f3f3', borderTop: '3px solid #3498db', borderRadius: '50%', animation: 'spin 1s linear infinite' }}></div>
                    <style>{`@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }`}</style>
                    <p style={{ fontWeight: '600' }}>{__('Processing Cutout...', 'personaliseit')}</p>
                    <div style={{ width: '100%', background: '#e0e0e0', borderRadius: '4px', height: '6px', overflow: 'hidden', marginTop: '8px' }}>
                        <div style={{ width: `${progress}%`, background: '#2271b1', height: '100%', transition: 'width 0.3s ease' }}></div>
                    </div>
                </div>
            ) : (
                <>
                    <h4 style={{ margin: '0 0 10px', fontSize: '14px' }}>{__('Upload Photo for Face Cutout', 'personaliseit')}</h4>

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
                        style={{ width: '100%', marginBottom: '10px', padding: '10px' }}
                    >
                        <span className="dashicons dashicons-camera" style={{ marginRight: '6px' }}></span>
                        {__('Select Photo', 'personaliseit')}
                    </button>

                    {error && <div style={{ color: '#d63638', fontSize: '12px', marginBottom: '10px' }}>{error}</div>}

                    <button
                        className="pi-btn secondary small"
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

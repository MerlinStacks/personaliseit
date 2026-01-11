/**
 * Background Removal Utilities
 */

import { __ } from '@wordpress/i18n';

// Helper: Simple White Background Removal (Luminance Key) for Line Art
export const removeWhiteBackground = async (imgUrl, addLog = () => { }) => {
    addLog(`Fallback: Starting White Removal on ${imgUrl}`);
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = "Anonymous";

        img.onload = () => {
            addLog('Fallback: Image Loaded. Processing Canvas...');
            try {
                const canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);

                const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imageData.data;
                const threshold = 240;
                let pixelsRemoved = 0;

                for (let i = 0; i < data.length; i += 4) {
                    if (data[i] > threshold && data[i + 1] > threshold && data[i + 2] > threshold) {
                        data[i + 3] = 0;
                        pixelsRemoved++;
                    }
                }
                addLog(`Fallback: Removed ${pixelsRemoved} white pixels.`);

                ctx.putImageData(imageData, 0, 0);
                canvas.toBlob((blob) => {
                    addLog(`Fallback: Blob created (${blob.size} bytes).`);
                    resolve(blob);
                }, 'image/png');
            } catch (e) {
                addLog('Fallback Error: ' + e.message);
                reject(e);
            }
        };

        img.onerror = (err) => {
            addLog('Fallback: Image Load Failed (CORS?).');
            reject(new Error('Image load failed'));
        };

        // Bust cache to prevent CORS issues with cached resources
        img.src = imgUrl + (imgUrl.includes('?') ? '&' : '?') + 't=' + Date.now();
    });
};

export const runBackgroundRemoval = async (imageUrl, addLog = () => { }, onProgress = null) => {
    addLog(`Background Removal Initiated for: ${imageUrl}`);
    let blob = null;

    try {
        // Priority 1: AI Background Removal
        // addLog('Priority 1: Attempting AI Model (@imgly)...');
        const imgly = await import('@imgly/background-removal');

        const config = {
            debug: false,
            device: 'gpu',
            progress: (key, current, total) => {
                // Approximate progress: Fetching vs Processing
                // Imgly usually does multiple steps. We'll forward the raw fraction for now.
                // Or simplistic: if total is known, use current/total. 
                // Often 'key' is 'compute:inference' or 'fetch:model'.
                let percent = 0;
                if (total > 0) {
                    percent = (current / total) * 100;
                }
                // If we are in inference, we might restart progress, so let's just forward it reliably
                if (onProgress) onProgress(key, percent);
            }
        };

        blob = await imgly.removeBackground(imageUrl, config);
        // addLog('Priority 1: AI Model Success!');

    } catch (error) {
        addLog('Priority 1 Failed: ' + error.message);
        console.error(error);
    }

    // Priority 2: Fallback
    if (!blob) {
        addLog('Priority 2: Fallback to Luminance Key...');
        try {
            blob = await removeWhiteBackground(imageUrl, addLog);
            addLog('Priority 2: Success!');
        } catch (fallbackErr) {
            addLog('Priority 2 Failed: ' + fallbackErr.message);
        }
    }

    return blob;
};

export const fileFromBlob = (blob, name) => {
    return new File([blob], name, { type: blob.type });
};

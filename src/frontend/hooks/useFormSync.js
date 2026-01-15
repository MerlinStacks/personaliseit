/**
 * useFormSync - Custom hook for syncing personalization data to cart form
 * 
 * Generates a debounced JSON payload for the hidden form input,
 * including inputs, styles, and preview image.
 */
import { useState, useEffect } from '@wordpress/element';

/**
 * Syncs user inputs, styles, and preview image to a JSON payload for cart submission
 * @param {Object} params - Hook parameters
 * @param {Object} params.userInputs - User input values
 * @param {Object} params.userStyles - User style selections
 * @param {string} params.embroideryColor - Selected embroidery thread color
 * @param {Object} params.stageRef - Ref to the Konva stage for preview generation
 * @param {number} params.debounceMs - Debounce delay in milliseconds (default: 800)
 * @returns {string} JSON-encoded form data payload
 */
const useFormSync = ({
    userInputs,
    userStyles,
    embroideryColor,
    stageRef,
    debounceMs = 800
}) => {
    const [formData, setFormData] = useState('');

    useEffect(() => {
        const syncTimer = setTimeout(() => {
            if (!stageRef || !stageRef.current) return;

            try {
                // Generate preview image (JPEG 0.8 for speed/size balance)
                const dataUrl = stageRef.current.toDataURL({
                    pixelRatio: 1,
                    mimeType: 'image/jpeg',
                    quality: 0.8
                });

                const payload = {
                    inputs: userInputs,
                    styles: userStyles,
                    embroideryColor: embroideryColor || null,
                    previewImage: dataUrl
                };

                setFormData(JSON.stringify(payload));
            } catch (e) {
                console.warn('PersonaliseIt Preview Generation Failed:', e);
                // Fallback without preview image
                const payload = {
                    inputs: userInputs,
                    styles: userStyles,
                    embroideryColor: embroideryColor || null
                };
                setFormData(JSON.stringify(payload));
            }
        }, debounceMs);

        return () => clearTimeout(syncTimer);
    }, [userInputs, userStyles, embroideryColor, stageRef, debounceMs]);

    return formData;
};

export default useFormSync;

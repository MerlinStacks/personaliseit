import { __ } from '@wordpress/i18n';
import { ToggleControl, Button, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import BackgroundRemovalService from '../../../../common/services/BackgroundRemovalService';
import { showToast } from '../../../../common/components/Toast';

const SettingsTab = ({ selectedLayer, updateLayer }) => {
    const [isRemovingBg, setIsRemovingBg] = useState(false);

    const handleRemoveBg = async () => {
        if (!selectedLayer.image) return;
        setIsRemovingBg(true);
        try {
            // Process
            const blob = await BackgroundRemovalService.removeBackground(selectedLayer.image);

            // Upload
            const formData = new FormData();
            formData.append('file', blob, 'cutout-layer.png');
            const response = await apiFetch({
                path: '/wp/v2/media',
                method: 'POST',
                body: formData,
            });

            // Update Layer
            updateLayer(selectedLayer.id, { image: response.source_url });
        } catch (error) {
            console.error('BG Removal failed:', error);
            showToast.error(__('Failed to remove background. See console.', 'personaliseit'));
        } finally {
            setIsRemovingBg(false);
        }
    };

    return (
        <>
            <div className="mb-3">
                <ToggleControl
                    label={__('Required Layer', 'personaliseit')}
                    help={__('User must provide input for this layer to add to cart.', 'personaliseit')}
                    checked={!!selectedLayer.required}
                    onChange={(val) => updateLayer(selectedLayer.id, { required: val })}
                />
            </div>

            <div className="section-divider mt-3 pt-3" style={{ marginTop: '15px', paddingTop: '15px', borderTop: '1px solid #eee' }}>
                <ToggleControl
                    label={__('Exclude from Export', 'personaliseit')}
                    checked={!!selectedLayer.excludeFromExport}
                    onChange={(val) => updateLayer(selectedLayer.id, { excludeFromExport: val })}
                    help={__('If checked, this layer will NOT appear in the final print file (PNG/PDF/SVG). Useful for guides or overlays.', 'personaliseit')}
                />
            </div>

            {selectedLayer.type === 'image' && (
                <div className="section-divider mt-3 pt-3" style={{ marginTop: '15px', paddingTop: '15px', borderTop: '1px solid #eee' }}>
                    <label style={{ display: 'block', marginBottom: '10px' }}>{__('AI Tools', 'personaliseit')}</label>
                    <Button
                        isSecondary
                        isSmall
                        onClick={handleRemoveBg}
                        disabled={isRemovingBg || !selectedLayer.image}
                        isBusy={isRemovingBg}
                        style={{ width: '100%', justifyContent: 'center' }}
                    >
                        {isRemovingBg ? __('Processing...', 'personaliseit') : __('Remove Background (AI)', 'personaliseit')}
                    </Button>
                    <p className="description" style={{ marginTop: '5px' }}>
                        {__('Uses browser-based AI to remove background. Result will replace the current image.', 'personaliseit')}
                    </p>
                </div>
            )}
        </>
    );
};

export default SettingsTab;

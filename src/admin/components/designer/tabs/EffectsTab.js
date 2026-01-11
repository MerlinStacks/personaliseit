import { __ } from '@wordpress/i18n';
import { SelectControl, ToggleControl } from '@wordpress/components';
import apiFetch from '@wordpress/api-fetch';

const EffectsTab = ({ selectedLayer, updateLayer, personalisationMethod, setIsLoading }) => {

    // Safety check for embroidery
    if (personalisationMethod === 'embroidery') return null;

    return (
        <>
            <div className="mb-3">
                <SelectControl
                    label={__('Mask Shape (Crop)', 'personaliseit')}
                    value={selectedLayer.maskShape || 'none'}
                    options={[
                        { label: __('None', 'personaliseit'), value: 'none' },
                        { label: __('Circle', 'personaliseit'), value: 'circle' },
                        { label: __('Oval', 'personaliseit'), value: 'oval' },
                        { label: __('Rounded Corners', 'personaliseit'), value: 'rounded_square' },
                        { label: __('Heart', 'personaliseit'), value: 'heart' },
                    ]}
                    onChange={(val) => updateLayer(selectedLayer.id, { maskShape: val })}
                />
            </div>

            <div className="mb-3">
                <SelectControl
                    label={__('Blend Mode', 'personaliseit')}
                    value={selectedLayer.blendMode || 'source-over'}
                    options={[
                        { label: __('Normal', 'personaliseit'), value: 'source-over' },
                        { label: __('Multiply', 'personaliseit'), value: 'multiply' },
                        { label: __('Screen', 'personaliseit'), value: 'screen' },
                        { label: __('Overlay', 'personaliseit'), value: 'overlay' },
                    ]}
                    onChange={(val) => updateLayer(selectedLayer.id, { blendMode: val })}
                />
            </div>

            {/* Pattern Fill */}
            <div className="effect-box mb-3" style={{ marginBottom: '15px' }}>
                <ToggleControl
                    label={__('Pattern Fill', 'personaliseit')}
                    checked={!!selectedLayer.fillPatternImage}
                    onChange={(val) => !val ? updateLayer(selectedLayer.id, { fillPatternImage: null }) : document.getElementById('pattern-upload-' + selectedLayer.id)?.click()}
                />
                <input id={'pattern-upload-' + selectedLayer.id} type="file" accept="image/*" style={{ display: 'none' }} onChange={async (e) => {
                    const file = e.target.files[0]; if (!file) return;
                    const fd = new FormData(); fd.append('file', file);
                    setIsLoading(true);
                    try { const res = await apiFetch({ path: '/wp/v2/media', method: 'POST', body: fd }); updateLayer(selectedLayer.id, { fillPatternImage: res.source_url }); } catch (err) { console.error(err); }
                    setIsLoading(false);
                }} />
                {selectedLayer.fillPatternImage && <img src={selectedLayer.fillPatternImage} alt="Pattern" className="effect-preview" style={{ width: '50px', height: '50px', border: '1px solid #ddd', marginTop: '5px' }} />}
            </div>

            {/* Distress Mask */}
            <div className="effect-box mb-3">
                <ToggleControl label={__('Distress Mask', 'personaliseit')} checked={!!selectedLayer.distressImage} onChange={(val) => !val ? updateLayer(selectedLayer.id, { distressImage: null }) : document.getElementById('distress-upload-' + selectedLayer.id)?.click()} />
                <input id={'distress-upload-' + selectedLayer.id} type="file" accept="image/*" style={{ display: 'none' }} onChange={async (e) => {
                    const file = e.target.files[0]; if (!file) return;
                    const fd = new FormData(); fd.append('file', file);
                    setIsLoading(true);
                    try { const res = await apiFetch({ path: '/wp/v2/media', method: 'POST', body: fd }); updateLayer(selectedLayer.id, { distressImage: res.source_url }); } catch (err) { console.error(err); }
                    setIsLoading(false);
                }} />
                {selectedLayer.distressImage && <img src={selectedLayer.distressImage} alt="Distress" className="effect-preview" style={{ width: '50px', height: '50px', border: '1px solid #ddd', marginTop: '5px' }} />}
            </div>
        </>
    );
};

export default EffectsTab;

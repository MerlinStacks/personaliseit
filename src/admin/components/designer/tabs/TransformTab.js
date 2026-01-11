import { __ } from '@wordpress/i18n';
import { CompactNumber } from '../DesignerControls';
import { RangeControl, SelectControl, TextControl } from '@wordpress/components';

const TransformTab = ({ selectedLayer, updateLayer }) => {
    return (
        <>
            <div className="flex gap-2 mb-3" style={{ display: 'flex', gap: '8px', marginBottom: '12px' }}>
                <CompactNumber label="X" value={selectedLayer.x} onChange={(v) => updateLayer(selectedLayer.id, { x: v })} />
                <CompactNumber label="Y" value={selectedLayer.y} onChange={(v) => updateLayer(selectedLayer.id, { y: v })} />
            </div>
            <div className="flex gap-2 mb-3" style={{ display: 'flex', gap: '8px', marginBottom: '12px' }}>
                <CompactNumber label={__('Width', 'personaliseit')} value={Math.abs(selectedLayer.width * (selectedLayer.scaleX || 1))} onChange={(v) => updateLayer(selectedLayer.id, { width: v, scaleX: 1, scaleY: 1 })} />
                <CompactNumber label={__('Height', 'personaliseit')} value={Math.abs(selectedLayer.height * (selectedLayer.scaleY || 1))} onChange={(v) => updateLayer(selectedLayer.id, { height: v, scaleX: 1, scaleY: 1 })} />
            </div>
            <div className="mb-3">
                <RangeControl label={__('Rotation', 'personaliseit')} value={selectedLayer.rotation || 0} onChange={(v) => updateLayer(selectedLayer.id, { rotation: v })} min={0} max={360} />
            </div>
            <div className="section-divider mt-3 pt-3" style={{ marginTop: '15px', paddingTop: '15px', borderTop: '1px solid #eee' }}>
                <strong style={{ display: 'block', marginBottom: 5 }}>{__('Pricing Rules', 'personaliseit')}</strong>
                <SelectControl
                    label={__('Pricing Type', 'personaliseit')}
                    value={selectedLayer.pricingType || 'fixed'}
                    options={[
                        { label: __('Fixed Price', 'personaliseit'), value: 'fixed' },
                        ...(selectedLayer.type === 'text' ? [
                            { label: __('Per Character', 'personaliseit'), value: 'per_char' },
                            { label: __('Per Line', 'personaliseit'), value: 'per_line' },
                        ] : [])
                    ]}
                    onChange={(val) => updateLayer(selectedLayer.id, { pricingType: val })}
                />
                <TextControl
                    label={__('Cost Amount (+)', 'personaliseit')}
                    type="number"
                    value={selectedLayer.price || ''}
                    onChange={(val) => updateLayer(selectedLayer.id, { price: val })}
                    help={selectedLayer.pricingType === 'per_char'
                        ? __('Cost added for EACH character.', 'personaliseit')
                        : __('Flat cost added when this layer is active.', 'personaliseit')}
                />
            </div>
        </>
    );
};

export default TransformTab;

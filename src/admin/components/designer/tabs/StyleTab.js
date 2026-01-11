import { __ } from '@wordpress/i18n';
import { CompactNumber, ColorControl } from '../DesignerControls';
import { Button, TextControl, SelectControl, ToggleControl, ButtonGroup, BaseControl, RangeControl, FormTokenField, CheckboxControl, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

const StyleTab = ({ selectedLayer, updateLayer, fonts, personalisationMethod, allCategories }) => {
    const [aiStyles, setAiStyles] = useState([]);
    const [isLoadingStyles, setIsLoadingStyles] = useState(false);

    useEffect(() => {
        if (selectedLayer.type === 'image') {
            setIsLoadingStyles(true);
            apiFetch({ path: '/wp/v2/personaliseit_style?_fields=id,title' })
                .then(data => {
                    if (Array.isArray(data)) {
                        setAiStyles(data.map(item => ({ id: item.id, title: item.title.rendered })));
                    }
                })
                .catch(err => console.error(err))
                .finally(() => setIsLoadingStyles(false));
        }
    }, [selectedLayer.type]);

    return (
        <>
            {/* Image Layer Specifics */}
            {selectedLayer.type === 'image' && (
                <div className="mb-3">
                    <h4 style={{ fontSize: '13px', margin: '0 0 10px', textTransform: 'uppercase', color: '#666' }}>
                        {personalisationMethod === 'embroidery' ? __('Auto-Apply AI Style', 'personaliseit') : __('Allowed AI Styles', 'personaliseit')}
                    </h4>
                    <p className="description" style={{ marginBottom: '10px' }}>
                        {personalisationMethod === 'embroidery'
                            ? __('Select the style that will be automatically applied to uploaded images for embroidery.', 'personaliseit')
                            : __('Limit available styles for this zone. Empty = All.', 'personaliseit')
                        }
                    </p>

                    {isLoadingStyles ? <Spinner /> : (
                        <div style={{ maxHeight: '150px', overflowY: 'auto', border: '1px solid #ddd', padding: '10px', borderRadius: '4px', background: '#fff' }}>
                            {aiStyles.length === 0 ? <p>{__('No styles found.', 'personaliseit')}</p> : (
                                personalisationMethod === 'embroidery' ? (
                                    // Single Select for Embroidery
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: '5px' }}>
                                        {aiStyles.map(style => (
                                            <div key={style.id} style={{ display: 'flex', alignItems: 'center', marginBottom: '4px' }}>
                                                <input
                                                    type="radio"
                                                    id={`style-radio-${style.id}`}
                                                    name="embroidery-style-select"
                                                    checked={(selectedLayer.allowedAiStyles && selectedLayer.allowedAiStyles[0] === style.id)}
                                                    onChange={() => updateLayer(selectedLayer.id, { allowedAiStyles: [style.id] })}
                                                    style={{ marginRight: '6px' }}
                                                />
                                                <label htmlFor={`style-radio-${style.id}`}>{style.title || __('(No Title)', 'personaliseit')}</label>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    // Multi Checkbox for Normal mode
                                    aiStyles.map(style => (
                                        <CheckboxControl
                                            key={style.id}
                                            label={style.title || __('(No Title)', 'personaliseit')}
                                            checked={(selectedLayer.allowedAiStyles || []).includes(style.id)}
                                            onChange={(checked) => {
                                                let current = selectedLayer.allowedAiStyles || [];
                                                if (checked) {
                                                    current = [...current, style.id];
                                                } else {
                                                    current = current.filter(id => id !== style.id);
                                                }
                                                updateLayer(selectedLayer.id, { allowedAiStyles: current });
                                            }}
                                        />
                                    ))
                                )
                            )}
                        </div>
                    )}
                </div>
            )}

            {/* Image Upload Validation Settings */}
            {selectedLayer.type === 'image' && (
                <div className="mb-3" style={{ marginTop: '15px', paddingTop: '15px', borderTop: '1px solid #eee' }}>
                    <h4 style={{ fontSize: '13px', margin: '0 0 10px', textTransform: 'uppercase', color: '#666' }}>
                        {__('Upload Validation', 'personaliseit')}
                    </h4>

                    {/* Allowed Formats */}
                    <div style={{ marginBottom: '12px' }}>
                        <BaseControl label={__('Allowed Formats', 'personaliseit')}>
                            <div style={{ display: 'flex', flexWrap: 'wrap', gap: '10px' }}>
                                {['jpg', 'png', 'webp', 'heic'].map(fmt => (
                                    <CheckboxControl
                                        key={fmt}
                                        label={fmt.toUpperCase()}
                                        checked={(selectedLayer.allowedFormats || ['jpg', 'png']).includes(fmt)}
                                        onChange={(checked) => {
                                            let current = selectedLayer.allowedFormats || ['jpg', 'png'];
                                            if (checked) {
                                                current = [...current, fmt];
                                            } else {
                                                current = current.filter(f => f !== fmt);
                                            }
                                            if (current.length === 0) current = ['jpg']; // At least one format
                                            updateLayer(selectedLayer.id, { allowedFormats: current });
                                        }}
                                    />
                                ))}
                            </div>
                        </BaseControl>
                    </div>

                    {/* File Size and Resolution */}
                    <div style={{ display: 'flex', gap: '10px', flexWrap: 'wrap', marginBottom: '12px' }}>
                        <CompactNumber
                            label={__('Max Size (MB)', 'personaliseit')}
                            value={selectedLayer.maxFileSizeMB || 10}
                            onChange={(v) => updateLayer(selectedLayer.id, { maxFileSizeMB: v })}
                            min={1} max={50}
                        />
                        <CompactNumber
                            label={__('Min Width (px)', 'personaliseit')}
                            value={selectedLayer.minWidth || 0}
                            onChange={(v) => updateLayer(selectedLayer.id, { minWidth: v })}
                            min={0} max={4000}
                        />
                        <CompactNumber
                            label={__('Min Height (px)', 'personaliseit')}
                            value={selectedLayer.minHeight || 0}
                            onChange={(v) => updateLayer(selectedLayer.id, { minHeight: v })}
                            min={0} max={4000}
                        />
                    </div>
                    <p style={{ fontSize: '11px', color: '#888', margin: '0 0 10px 0' }}>
                        {__('Set to 0 to disable resolution check.', 'personaliseit')}
                    </p>

                    {/* Background Removal Toggle */}
                    <CheckboxControl
                        label={__('Enable Background Removal', 'personaliseit')}
                        checked={selectedLayer.enableBackgroundRemoval || false}
                        onChange={(checked) => updateLayer(selectedLayer.id, { enableBackgroundRemoval: checked })}
                        help={__('Shows "Remove Background" button for customers (self-hosted AI, no API key needed)', 'personaliseit')}
                    />
                </div>
            )}


            {/* Text Specifics */}
            {selectedLayer.type === 'text' && (
                <div>
                    <div className="mb-3">
                        <BaseControl label={__('Font Family', 'personaliseit')}>
                            <select
                                className="components-select-control__input"
                                value={selectedLayer.fontFamily}
                                onChange={(e) => updateLayer(selectedLayer.id, { fontFamily: e.target.value })}
                                style={{ fontFamily: selectedLayer.fontFamily }}
                            >
                                {fonts.map((f) => (
                                    <option key={f.family} value={f.family} style={{ fontFamily: f.family }}>
                                        {f.title}
                                    </option>
                                ))}
                            </select>
                        </BaseControl>
                    </div>

                    <div className="flex gap-2 mb-3" style={{ display: 'flex', gap: '8px', marginBottom: '12px', flexWrap: 'wrap' }}>
                        <CompactNumber
                            label={__('Size (Max)', 'personaliseit')}
                            value={selectedLayer.fontSize || 30}
                            onChange={(v) => updateLayer(selectedLayer.id, { fontSize: v })}
                            min={8} max={300}
                        />
                        <CompactNumber
                            label={__('Min Size', 'personaliseit')}
                            value={selectedLayer.minFontSize || 10}
                            onChange={(v) => updateLayer(selectedLayer.id, { minFontSize: v })}
                            min={5} max={100}
                        />
                        <CompactNumber
                            label={__('Min Chars', 'personaliseit')}
                            value={selectedLayer.minLength || 0}
                            onChange={(v) => updateLayer(selectedLayer.id, { minLength: v })}
                            min={0} max={100}
                        />
                        <CompactNumber
                            label={__('Max Chars', 'personaliseit')}
                            value={selectedLayer.maxLength || 30}
                            onChange={(v) => updateLayer(selectedLayer.id, { maxLength: v })}
                            min={1} max={500}
                        />
                    </div>
                    <div className="flex gap-2 mb-3" style={{ display: 'flex', gap: '8px', marginBottom: '12px' }}>
                        <CompactNumber
                            label={__('Spacing', 'personaliseit')}
                            value={selectedLayer.letterSpacing || 0}
                            onChange={(v) => updateLayer(selectedLayer.id, { letterSpacing: v })}
                            min={-10} max={50}
                        />
                        <CompactNumber
                            label={__('Line Height', 'personaliseit')}
                            value={selectedLayer.lineHeight || 1.2}
                            onChange={(v) => updateLayer(selectedLayer.id, { lineHeight: v })}
                            step={0.1} min={0.5} max={3}
                        />
                        <div style={{ flex: 1 }}>
                            <ColorControl
                                label={__('Color', 'personaliseit')}
                                color={selectedLayer.color}
                                onChange={(val) => updateLayer(selectedLayer.id, { color: val })}
                            />
                        </div>
                    </div>

                    {/* TEXT WARP */}
                    {personalisationMethod !== 'embroidery' && (
                        <div className="section-divider mt-3 pt-3" style={{ marginTop: '15px', paddingTop: '15px', borderTop: '1px solid #eee' }}>
                            <SelectControl
                                label={__('Text Warp', 'personaliseit')}
                                value={selectedLayer.warpStyle || (selectedLayer.isCurved ? 'arc' : 'none')}
                                options={[
                                    { label: __('None', 'personaliseit'), value: 'none' },
                                    { label: __('Arc (Curved)', 'personaliseit'), value: 'arc' },
                                    { label: __('Bridge (Arch)', 'personaliseit'), value: 'bridge' },
                                    { label: __('Bulge', 'personaliseit'), value: 'bulge' },
                                ]}
                                onChange={(val) => updateLayer(selectedLayer.id, { warpStyle: val, isCurved: val === 'arc' })}
                            />
                            {selectedLayer.warpStyle && selectedLayer.warpStyle !== 'none' && (
                                <RangeControl
                                    label={__('Intensity', 'personaliseit')}
                                    value={selectedLayer.warpAmount || selectedLayer.radius || 50}
                                    onChange={(v) => updateLayer(selectedLayer.id, { warpAmount: v, radius: v })}
                                    min={-200} max={200} step={5}
                                />
                            )}
                        </div>
                    )}
                </div>
            )}

            {selectedLayer.type === 'clipart' && (
                <div className="mb-3">
                    <FormTokenField
                        label={__('Allowed Categories', 'personaliseit')}
                        value={selectedLayer.allowedCategories || []}
                        suggestions={allCategories}
                        onChange={(tokens) => updateLayer(selectedLayer.id, { allowedCategories: tokens })}
                        help={__('Leave empty to allow all.', 'personaliseit')}
                    />
                </div>
            )}

            {/* Spotify Layer Options */}
            {selectedLayer.type === 'spotify' && (
                <div>
                    <h4 style={{ fontSize: '13px', margin: '0 0 10px', textTransform: 'uppercase', color: '#666' }}>
                        {__('Spotify Code Style', 'personaliseit')}
                    </h4>

                    <div className="mb-3" style={{ marginBottom: '12px' }}>
                        <ColorControl
                            label={__('Bar Color', 'personaliseit')}
                            color={selectedLayer.spotifyBarColor || '#000000'}
                            onChange={(val) => {
                                // Regenerate image URL with new bar color
                                const barColor = val.replace('#', '');
                                const uri = selectedLayer.spotifyUri;
                                if (uri) {
                                    const newImage = `/wp-json/personaliseit/v1/spotify/code?raw=1&format=svg&bg=000000&color=${barColor}&uri=${encodeURIComponent(uri)}`;
                                    updateLayer(selectedLayer.id, { spotifyBarColor: val, image: newImage });
                                } else {
                                    updateLayer(selectedLayer.id, { spotifyBarColor: val });
                                }
                            }}
                        />
                        <p style={{ fontSize: '11px', color: '#888', marginTop: '5px' }}>
                            {__('Background removal is applied automatically for transparency.', 'personaliseit')}
                        </p>
                    </div>

                    <div className="section-divider mt-3 pt-3" style={{ marginTop: '15px', paddingTop: '15px', borderTop: '1px solid #eee' }}>
                        <h4 style={{ fontSize: '13px', margin: '0 0 10px', textTransform: 'uppercase', color: '#666' }}>
                            {__('Metadata Display', 'personaliseit')}
                        </h4>
                        <p className="description" style={{ marginBottom: '10px', fontSize: '11px', color: '#888' }}>
                            {__('Show song/album info below the code. Requires API search or manual entry.', 'personaliseit')}
                        </p>
                        <ToggleControl
                            label={__('Show Title', 'personaliseit')}
                            checked={!!selectedLayer.spotifyShowTitle}
                            onChange={(val) => updateLayer(selectedLayer.id, { spotifyShowTitle: val })}
                        />
                        <ToggleControl
                            label={__('Show Artist', 'personaliseit')}
                            checked={!!selectedLayer.spotifyShowArtist}
                            onChange={(val) => updateLayer(selectedLayer.id, { spotifyShowArtist: val })}
                        />
                        {(selectedLayer.spotifyShowTitle || selectedLayer.spotifyShowArtist) && (
                            <>
                                <TextControl
                                    label={__('Default Title', 'personaliseit')}
                                    value={selectedLayer.spotifyDefaultTitle || ''}
                                    onChange={(val) => updateLayer(selectedLayer.id, { spotifyDefaultTitle: val })}
                                    placeholder={__('e.g., Shape of You', 'personaliseit')}
                                />
                                <TextControl
                                    label={__('Default Artist', 'personaliseit')}
                                    value={selectedLayer.spotifyDefaultArtist || ''}
                                    onChange={(val) => updateLayer(selectedLayer.id, { spotifyDefaultArtist: val })}
                                    placeholder={__('e.g., Ed Sheeran', 'personaliseit')}
                                />
                                <div style={{ flex: 1 }}>
                                    <ColorControl
                                        label={__('Text Color', 'personaliseit')}
                                        color={selectedLayer.spotifyTextColor || '#ffffff'}
                                        onChange={(val) => updateLayer(selectedLayer.id, { spotifyTextColor: val })}
                                    />
                                </div>
                            </>
                        )}
                    </div>
                </div>
            )}


        </>
    );
};

export default StyleTab;

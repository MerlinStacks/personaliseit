import { __ } from '@wordpress/i18n';

const TextLayerControl = ({
    layer,
    userInputs,
    userStyles,
    fonts,
    updateInput,
    updateStyle,
    handleAddToCart,
    personalisationMethod,
    activePalette,
    labelPosition = 'above'
}) => {
    const flexDirection = labelPosition === 'left' ? 'row' : labelPosition === 'right' ? 'row-reverse' : 'column';
    const alignItems = (labelPosition === 'left' || labelPosition === 'right') ? 'center' : 'stretch';
    const labelOrder = labelPosition === 'below' ? 2 : 0;
    const inputOrder = 1;
    const gap = '10px';

    const MAX_CHARS = layer.maxLength || 30;
    const MIN_CHARS = layer.minLength || 0;
    const currentLength = (userInputs[layer.id] || '').length;
    const isTooShort = MIN_CHARS > 0 && currentLength < MIN_CHARS && currentLength > 0;
    const isValid = currentLength >= MIN_CHARS || MIN_CHARS === 0;

    return (
        <div className="pi-layer-group" style={{ marginBottom: '15px' }}>
            {/* Row 1: Text Input + Toolbar */}
            <div className="control-group" style={{ display: 'flex', flexDirection: flexDirection, alignItems: alignItems, gap: gap, marginBottom: '10px' }}>
                <label htmlFor={`personaliseit-input-${layer.id}`} style={{ order: labelOrder, fontWeight: '600', marginBottom: '5px', minWidth: labelPosition === 'left' ? '120px' : 'auto' }}>
                    {layer.label || __('Enter Text', 'personaliseit')}
                    {layer.required && <span style={{ color: '#d63638', marginLeft: '3px' }}>*</span>}
                </label>
                <div style={{ order: inputOrder, flex: 1, width: '100%' }}>
                    <input
                        id={`personaliseit-input-${layer.id}`}
                        type="text"
                        className={`pi-modern-input ${isTooShort ? 'pi-input-error' : ''}`}
                        value={userInputs[layer.id] || ''}
                        onChange={(e) => {
                            let val = e.target.value;
                            if (val.length > MAX_CHARS) {
                                val = val.substring(0, MAX_CHARS);
                            }
                            updateInput(layer.id, val);
                            handleAddToCart();
                        }}
                        maxLength={MAX_CHARS}
                        placeholder={layer.label}
                        style={{
                            width: '100%',
                            borderColor: isTooShort ? '#d63638' : undefined,
                            boxShadow: isTooShort ? '0 0 0 1px #d63638' : undefined
                        }}
                    />
                    <div style={{
                        fontSize: '11px',
                        color: isTooShort ? '#d63638' : '#666',
                        textAlign: 'right',
                        marginTop: '2px',
                        display: 'flex',
                        justifyContent: 'space-between'
                    }}>
                        {isTooShort ? (
                            <span>{__('Minimum', 'personaliseit')} {MIN_CHARS} {__('characters required', 'personaliseit')}</span>
                        ) : (
                            <span></span>
                        )}
                        <span>{currentLength} / {MAX_CHARS}</span>
                    </div>


                    {/* Modern Condensed Toolbar: Color | Effects Toggle */}
                    {personalisationMethod !== 'embroidery' && (
                        <div className="pi-toolbar">
                            {/* Color Selection (Compact) */}
                            {((activePalette && activePalette.colors && activePalette.colors.length > 1) || (!activePalette && personalisationMethod !== 'engraving')) && (
                                <div className="personaliseit-color-control" style={{ display: 'flex', alignItems: 'center' }}>
                                    {activePalette ? (
                                        <div style={{ display: 'flex', flexWrap: 'wrap', gap: '3px' }}>
                                            {activePalette.colors.map((color, idx) => {
                                                const isObj = typeof color === 'object' && color !== null;
                                                const code = isObj ? color.code : color;
                                                const name = isObj ? color.name : color;

                                                return (
                                                    <div
                                                        key={idx}
                                                        onClick={() => {
                                                            updateStyle(layer.id, { color: code });
                                                            handleAddToCart();
                                                        }}
                                                        style={{
                                                            width: '24px', height: '24px', background: code,
                                                            border: (userStyles[layer.id]?.color || layer.color) === code ? '2px solid #2271b1' : '1px solid #ddd',
                                                            cursor: 'pointer', borderRadius: '50%'
                                                        }}
                                                        title={name}
                                                    />
                                                );
                                            })}
                                        </div>
                                    ) : (
                                        <input
                                            type="color"
                                            onChange={(e) => {
                                                updateStyle(layer.id, { color: e.target.value });
                                                handleAddToCart();
                                            }}
                                            value={userStyles[layer.id]?.color || layer.color || '#000000'}
                                            style={{ width: '30px', height: '30px', padding: 0, border: 'none', background: 'none', cursor: 'pointer' }}
                                            title={__('Choose Color', 'personaliseit')}
                                        />
                                    )}
                                </div>
                            )}

                            {/* Effects Toggle Button */}
                            <button
                                className={`pi-icon-btn ${userStyles[layer.id]?.showEffects ? 'active' : ''}`}
                                onClick={() => {
                                    const current = userStyles[layer.id]?.showEffects;
                                    updateStyle(layer.id, { showEffects: !current });
                                }}
                                title={__('Text Effects', 'personaliseit')}
                            >
                                <span className="dashicons dashicons-art"></span>
                            </button>
                        </div>
                    )}

                    {/* Expanded Effects Panel */}
                    {personalisationMethod !== 'embroidery' && userStyles[layer.id]?.showEffects && (
                        <div className="pi-effect-panel">
                            <div className="pi-flex-row pi-wrap">
                                <div style={{ flex: 1, minWidth: '100px' }}>
                                    <label style={{ fontSize: '0.85em', display: 'block', marginBottom: '3px' }}>{__('Warp Style:', 'personaliseit')}</label>
                                    <select
                                        className="pi-modern-select"
                                        value={userStyles[layer.id]?.warpStyle || layer.warpStyle || 'none'}
                                        onChange={(e) => {
                                            updateStyle(layer.id, { warpStyle: e.target.value });
                                            handleAddToCart();
                                        }}
                                        style={{ width: '100%' }}
                                    >
                                        <option value="none">{__('None', 'personaliseit')}</option>
                                        <option value="arc">{__('Curved (Arc)', 'personaliseit')}</option>
                                        <option value="bridge">{__('Bridge', 'personaliseit')}</option>
                                        <option value="bulge">{__('Bulge', 'personaliseit')}</option>
                                    </select>
                                </div>

                                {(userStyles[layer.id]?.warpStyle || layer.warpStyle) && (userStyles[layer.id]?.warpStyle !== 'none' && layer.warpStyle !== 'none') && (
                                    <div style={{ flex: 1, minWidth: '100px' }}>
                                        <label style={{ fontSize: '0.85em', display: 'block', marginBottom: '3px' }}>
                                            {__('Intensity:', 'personaliseit')} {userStyles[layer.id]?.warpAmount !== undefined ? userStyles[layer.id].warpAmount : (layer.warpAmount || 50)}
                                        </label>
                                        <input
                                            type="range"
                                            min="-100" max="100" step="5"
                                            value={userStyles[layer.id]?.warpAmount !== undefined ? userStyles[layer.id].warpAmount : (layer.warpAmount || 50)}
                                            onChange={(e) => {
                                                const val = parseInt(e.target.value);
                                                updateStyle(layer.id, { warpAmount: val, radius: val });
                                                handleAddToCart();
                                            }}
                                            style={{ width: '100%' }}
                                        />
                                    </div>
                                )}
                            </div>


                        </div>
                    )}
                </div>
            </div>

            {/* Row 2: Font Selector */}
            <div className="control-group" style={{ display: 'flex', flexDirection: flexDirection, alignItems: alignItems, gap: gap }}>
                <label style={{ order: labelOrder, fontWeight: '600', fontSize: '0.9em', whiteSpace: 'nowrap', minWidth: labelPosition === 'left' ? '120px' : 'auto' }}>
                    {__('Font Choice:', 'personaliseit')}
                </label>
                <div style={{ order: inputOrder, flex: 1, width: '100%' }}>
                    <select
                        className="pi-modern-select"
                        onChange={(e) => {
                            updateStyle(layer.id, { fontFamily: e.target.value });
                            handleAddToCart();
                        }}
                        value={userStyles[layer.id]?.fontFamily || layer.fontFamily || 'Arial'}
                        style={{ width: '100%' }}
                        title={__('Choose Font', 'personaliseit')}
                    >
                        {fonts.length > 0 ? (
                            fonts.map((font) => (
                                <option key={font.id} value={font.family} style={{ fontFamily: font.family }}>
                                    {font.title}
                                </option>
                            ))
                        ) : (
                            <>
                                <option value="Arial">Arial</option>
                                <option value="Times New Roman">Times New Roman</option>
                                <option value="Courier New">Courier New</option>
                            </>
                        )}
                    </select>
                </div>
            </div>
        </div>
    );
};


export default TextLayerControl;
